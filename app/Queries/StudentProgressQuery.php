<?php

namespace App\Queries;

use App\Enums\Badge;
use App\Enums\MeetingStatus;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use App\Models\UserBadge;
use App\Support\ChurchCalendar;
use App\Support\StudyStreak;
use Illuminate\Support\Facades\DB;

/**
 * Progresso de um aluno numa classe: lição a lição (leitura em casa e presença), sequência e selos. Usado em "Meu progresso" e, pelo professor,
 * na página do aluno. Nunca inclui as anotações pessoais.
 */
class StudentProgressQuery
{
    public function __construct(
        private readonly StudyStreak $streak,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user, Classroom $classroom, int $limit = 12): array
    {
        $today = ChurchCalendar::today()->toDateString();

        $lessons = Lesson::query()
            ->whereBelongsTo($classroom)
            ->visible()
            ->whereHas('meetings', fn ($q) => $q->active()->whereDate('held_on', '<=', $today))
            ->withMax(['meetings as last_meeting_on' => fn ($q) => $q->active()->whereDate('held_on', '<=', $today)], 'held_on')
            ->withCount(['readings' => fn ($q) => $q->whereNotNull('weekday')])
            ->orderByDesc('last_meeting_on')
            ->limit($limit)
            ->get();

        $ids = $lessons->pluck('id');

        $daysRead = DB::table('reading_checkins')
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $ids)
            ->groupBy('lesson_id')
            ->selectRaw('lesson_id, COUNT(DISTINCT weekday) AS days')
            ->pluck('days', 'lesson_id');

        $attendance = DB::table('class_meetings')
            ->leftJoin('attendances', fn ($join) => $join
                ->on('attendances.class_meeting_id', '=', 'class_meetings.id')
                ->where('attendances.user_id', $user->id))
            ->whereIn('class_meetings.lesson_id', $ids)
            ->whereNotNull('class_meetings.attendance_taken_at')
            ->where('class_meetings.status', MeetingStatus::Held->value)
            ->groupBy('class_meetings.lesson_id')
            ->selectRaw('class_meetings.lesson_id, COUNT(*) AS meetings, COUNT(attendances.id) AS present')
            ->get()
            ->keyBy('lesson_id');

        return [
            'streak' => $this->streak->for($user),
            'badges' => UserBadge::query()
                ->where('user_id', $user->id)
                ->with('series:id,title')
                ->orderByDesc('awarded_at')
                ->get()
                ->map(fn (UserBadge $b) => [
                    'badge' => $b->badge->value,
                    'label' => $b->badge->label(),
                    'description' => $b->badge->description(),
                    'emoji' => $b->badge->emoji(),
                    'series' => $b->series?->title,
                    'awarded_at' => ChurchCalendar::formatShort($b->awarded_at),
                ]),
            'available_badges' => array_map(fn (Badge $b) => [
                'badge' => $b->value,
                'label' => $b->label(),
                'description' => $b->description(),
                'emoji' => $b->emoji(),
            ], Badge::cases()),
            'lessons' => $lessons->map(function (Lesson $lesson) use ($daysRead, $attendance) {
                $presence = $attendance->get($lesson->id);

                return [
                    'id' => $lesson->id,
                    'slug' => $lesson->slug,
                    'display_title' => $lesson->displayTitle(),
                    'date_short' => $lesson->getAttribute('last_meeting_on')
                        ? ChurchCalendar::formatShort(new \DateTimeImmutable((string) $lesson->getAttribute('last_meeting_on')))
                        : null,
                    'days_read' => (int) ($daysRead[$lesson->id] ?? 0),
                    'readings_total' => max((int) $lesson->getAttribute('readings_count'), 1),
                    'meetings' => $presence ? (int) $presence->meetings : 0,
                    'present' => $presence ? (int) $presence->present : 0,
                ];
            })->values(),
        ];
    }
}
