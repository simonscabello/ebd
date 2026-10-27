<?php

namespace App\Queries;

use App\Actions\Engagement\AwardBadges;
use App\Enums\Badge;
use App\Enums\MeetingStatus;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use App\Models\UserBadge;
use App\Support\ChurchCalendar;
use App\Support\Enrollment;
use App\Support\StudyStreak;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Progresso de um aluno numa classe: lição a lição (leitura em casa e presença), sequência e selos. Usado em "Meu progresso" e, pelo professor,
 * na página do aluno. Nunca inclui as anotações pessoais. A lição atual vem primeiro, mesmo antes do encontro: é a semana em curso.
 * Cada lição diz a série dela e a posição na série, para "Meu progresso" agrupar por série.
 */
class StudentProgressQuery
{
    public function __construct(
        private readonly StudyStreak $streak,
        private readonly AwardBadges $badges,
    ) {}

    /**
     * @return array{
     *     streak: array{current: int, best: int, today_done: bool},
     *     badges: Collection<int, array{badge: value-of<Badge>, label: string, description: string, emoji: string, series: string|null, awarded_at: string}>,
     *     available_badges: list<array{badge: value-of<Badge>, label: string, description: string, emoji: string, progress: array{current: int, target: int}|null}>,
     *     lessons: Collection<int, array{id: int, slug: string, display_title: string, date_short: string|null, days_read: int, readings_total: int, meetings: int, present: int, current: bool, position: int|null, series: array{id: int, title: string, current: bool, concluded_on: string|null}|null}>,
     * }
     */
    public function for(User $user, Classroom $classroom, int $limit = 12): array
    {
        $today = ChurchCalendar::today()->toDateString();

        $currentId = $classroom->current_lesson_id;

        $lessons = Lesson::query()
            ->whereBelongsTo($classroom)
            ->visible()
            ->where(fn ($q) => $q
                ->whereHas('meetings', fn ($q) => $q->active()->whereDate('held_on', '<=', $today))
                ->when($currentId, fn ($q, int $id) => $q->orWhereKey($id)))
            ->with('series:id,title,concluded_at')
            ->withMax(['meetings as last_meeting_on' => fn ($q) => $q->active()->whereDate('held_on', '<=', $today)], 'held_on')
            ->withCount(['readings' => fn ($q) => $q->whereNotNull('weekday')])
            ->when($currentId, fn ($q, int $id) => $q->orderByRaw('lessons.id = ? DESC', [$id]))
            ->orderByDesc('last_meeting_on')
            ->limit($limit)
            ->get();

        // Posição de cada lição na ordem da própria série ("Lição 5").
        $sequences = [];

        foreach (Lesson::query()->whereIn('series_id', $lessons->pluck('series_id')->filter()->unique())->inSequence()->get(['id', 'series_id']) as $item) {
            $sequences[(int) $item->series_id][] = $item->id;
        }

        $ids = $lessons->pluck('id');

        $daysRead = DB::table('reading_checkins')
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $ids)
            ->groupBy('lesson_id')
            ->selectRaw('lesson_id, COUNT(DISTINCT weekday) AS days')
            ->pluck('days', 'lesson_id');

        // Só os domingos desde que o aluno conta na classe (ver Enrollment).
        $since = Enrollment::sinceMap($classroom->id, [$user->id])[$user->id] ?? null;

        $attendance = DB::table('class_meetings')
            ->leftJoin('attendances', fn ($join) => $join
                ->on('attendances.class_meeting_id', '=', 'class_meetings.id')
                ->where('attendances.user_id', $user->id))
            ->whereIn('class_meetings.lesson_id', $ids)
            ->when($since, fn ($query, string $date) => $query->whereDate('class_meetings.held_on', '>=', $date))
            ->whereNotNull('class_meetings.attendance_taken_at')
            ->where('class_meetings.status', MeetingStatus::Held->value)
            ->groupBy('class_meetings.lesson_id')
            ->selectRaw('class_meetings.lesson_id, COUNT(*) AS meetings, COUNT(attendances.id) AS present')
            ->get()
            ->keyBy('lesson_id');

        // Selos por série: a série da lição dada mais recentemente.
        $series = Series::query()->find($lessons->first()?->series_id);
        $progress = $this->badges->progress($user, $series);

        // Sequência e selos só desta igreja: quem está em duas não vê (nem
        // mostra ao professor) séries e datas da outra.
        $churchClassrooms = Classroom::query()->where('church_id', $classroom->church_id)->select('id');

        return [
            'streak' => $this->streak->for($user, churchId: $classroom->church_id),
            'badges' => UserBadge::query()
                ->where('user_id', $user->id)
                ->where(fn ($query) => $query
                    ->whereNull('series_id')
                    ->orWhereIn('series_id', Series::query()->whereIn('classroom_id', $churchClassrooms)->select('id')))
                ->with('series:id,title')
                ->orderByDesc('awarded_at')
                ->get()
                ->map(fn (UserBadge $b) => [
                    'badge' => $b->badge->value,
                    'label' => $b->badge->label($classroom->meeting_weekday),
                    'description' => $b->badge->description($classroom->meeting_weekday),
                    'emoji' => $b->badge->emoji(),
                    'series' => $b->series?->title,
                    'awarded_at' => ChurchCalendar::formatShort($b->awarded_at),
                ]),
            'available_badges' => array_map(fn (Badge $b) => [
                'badge' => $b->value,
                'label' => $b->label($classroom->meeting_weekday),
                'description' => $b->description($classroom->meeting_weekday),
                'emoji' => $b->emoji(),
                'progress' => $progress[$b->value] ?? null,
            ], Badge::cases()),
            'lessons' => $lessons->map(function (Lesson $lesson) use ($daysRead, $attendance, $currentId, $sequences, $classroom) {
                $presence = $attendance->get($lesson->id);
                $position = array_search($lesson->id, $sequences[$lesson->series_id ?? 0] ?? [], true);

                return [
                    'id' => $lesson->id,
                    'slug' => $lesson->slug,
                    'display_title' => $lesson->displayTitle(),
                    'date_short' => $lesson->getAttribute('last_meeting_on')
                        ? ChurchCalendar::formatShort(new \DateTimeImmutable((string) $lesson->getAttribute('last_meeting_on')))
                        : null,
                    'days_read' => (int) ($daysRead[$lesson->id] ?? 0),
                    'readings_total' => (int) $lesson->getAttribute('readings_count'),
                    'meetings' => $presence ? (int) $presence->meetings : 0,
                    'present' => $presence ? (int) $presence->present : 0,
                    'current' => $lesson->id === $currentId,
                    // O número da revista; sem número, a posição na série.
                    'position' => $lesson->number ?? ($position === false ? null : $position + 1),
                    'series' => $lesson->series === null ? null : [
                        'id' => $lesson->series->id,
                        'title' => $lesson->series->title,
                        'current' => $lesson->series->id === $classroom->current_series_id,
                        'concluded_on' => $lesson->series->concluded_at ? ChurchCalendar::formatShort($lesson->series->concluded_at) : null,
                    ],
                ];
            })->values(),
        ];
    }
}
