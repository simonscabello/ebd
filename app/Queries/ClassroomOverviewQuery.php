<?php

namespace App\Queries;

use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use App\Queries\Data\AttendanceBook;
use App\Queries\Data\CurrentLesson;
use App\Queries\Data\Period;
use App\Support\ChurchCalendar;
use App\Support\WeeklyMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Resumo da classe para o professor (tela Resumo e ferramenta
 * get_classroom_overview do MCP): este domingo, o último, pendências, quem
 * precisa de atenção, aniversariantes do mês e estudo em casa da lição atual.
 * Datas saem em Y-m-d; a tela formata.
 */
class ClassroomOverviewQuery
{
    /** Pendências: domingos passados ainda "planejados" nas últimas semanas. */
    private const PENDING_WEEKS = 8;

    public function __construct(
        private readonly CurrentLessonQuery $current,
        private readonly AttendanceBookQuery $books,
        private readonly HomeStudyQuery $homeStudy,
        private readonly StudentsNeedingAttention $needingAttention,
        private readonly WeeklyMessage $weeklyMessage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Classroom $classroom, User $user): array
    {
        $today = ChurchCalendar::today();
        $todayString = $today->toDateString();
        $week = $this->current->for($classroom, $user);

        // Aula de hoje já encerrada: o Resumo passa para o próximo domingo
        // (dá para mandar a lição seguinte no grupo no mesmo dia). Hoje vira o
        // "último domingo". Sem próximo domingo, continua mostrando o de hoje.
        if ($this->finishedToday($week, $todayString)) {
            $next = $this->current->for($classroom, $user, $today->addDay());

            if ($next->meeting !== null && ! $next->isFallback) {
                $week = $next;
            }
        }

        $book = $this->books->for($classroom);

        // Sem domingo pela frente, a "lição da semana" é a do último domingo:
        // no Resumo ela aparece como último domingo, não como o próximo.
        $meeting = $week->isFallback ? null : $week->meeting;
        $lesson = $meeting !== null ? $week->lesson : null;

        $study = $this->currentStudy($week, $book);

        $before = $meeting?->held_on->toDateString() ?? $todayString;
        $lastSunday = $book->meetings->reverse()->first(
            fn (ClassMeeting $m) => $meeting === null ? $m->held_on->toDateString() <= $todayString : $m->held_on->toDateString() < $before,
        );

        $period = Period::current($lesson?->series);

        return [
            'today' => $todayString,
            'week' => $meeting === null ? null : [
                'meeting' => [
                    'id' => $meeting->id,
                    'held_on' => $meeting->held_on->toDateString(),
                    'status' => $meeting->status->value,
                    'status_label' => $meeting->status->label(),
                    'title' => $meeting->title,
                    'has_attendance' => $meeting->hasAttendance(),
                    'is_today' => $meeting->held_on->toDateString() === $todayString,
                    'notes' => $meeting->notes,
                ],
                'lesson' => $lesson === null ? null : [
                    'id' => $lesson->id,
                    'slug' => $lesson->slug,
                    'display_title' => $lesson->displayTitle(),
                    'status' => $lesson->status->value,
                ],
                'meeting_index' => $week->meetingIndex,
                'meeting_total' => $week->meetingTotal,
                'cancelled_before' => $week->cancelledBefore->map(fn (ClassMeeting $m) => [
                    'held_on' => $m->held_on->toDateString(),
                    'title' => $m->title,
                ])->values()->all(),
                'can_take_attendance' => $meeting->held_on->toDateString() <= $todayString,
                'summary' => $meeting->hasAttendance() ? $book->sunday($meeting) : null,
                'weekly_message' => $lesson !== null ? $this->weeklyMessage->for($meeting) : null,
            ],
            'last_sunday' => $lastSunday === null ? null : [
                ...$this->sundayRow($lastSunday, $book),
                'notes' => $lastSunday->notes,
            ],
            'recent' => $book->meetings->reverse()->take(6)
                ->map(fn (ClassMeeting $m) => $this->sundayRow($m, $book))
                ->values()->all(),
            'pending' => ClassMeeting::query()
                ->whereBelongsTo($classroom)
                ->where('status', MeetingStatus::Planned)
                ->whereDate('held_on', '<', $todayString)
                ->whereDate('held_on', '>=', $today->subWeeks(self::PENDING_WEEKS)->toDateString())
                ->orderByDesc('held_on')
                ->with('lesson')
                ->get()
                ->map(fn (ClassMeeting $m) => [
                    'id' => $m->id,
                    'held_on' => $m->held_on->toDateString(),
                    'lesson' => $m->lesson?->displayTitle(),
                    'title' => $m->title,
                ])->all(),
            'attention' => $this->attention($classroom, $book, $study),
            'home_study' => $study === null ? null : array_diff_key($study, ['days' => true]),
            'birthdays' => $this->birthdays($book, $today->month, $today->day),
            'stats' => [
                'students' => $book->students->count(),
                'frequency' => $book->totals($period),
                'period' => ['label' => $period->label, 'series_id' => $period->seriesId, 'from' => $period->from, 'to' => $period->to],
            ],
            'thresholds' => [
                'missed_meetings' => (int) config('ebd.insights.missed_meetings'),
                'inactive_days' => (int) config('ebd.insights.inactive_days'),
                'new_student_days' => StudentsNeedingAttention::NEW_STUDENT_DAYS,
            ],
        ];
    }

    /**
     * Próximas lições da classe (uma por lição, no primeiro domingo ainda por
     * acontecer), com a mensagem pronta para o grupo. A aula de hoje já
     * encerrada fica de fora.
     *
     * @return LengthAwarePaginator<int, array{meeting_id: int, held_on: string, is_today: bool, meetings_count: int, lesson: array{id: int, slug: string, display_title: string, bible_reference: string|null, status: string}, message: string|null}>
     */
    public function upcoming(Classroom $classroom, int $perPage = 4): LengthAwarePaginator
    {
        $today = ChurchCalendar::todayString();
        $upcoming = fn ($q) => $q->whereBelongsTo($classroom)
            ->where('status', MeetingStatus::Planned)
            ->whereDate('held_on', '>=', $today);

        $page = Lesson::query()
            ->whereHas('meetings', $upcoming)
            ->withMin(['meetings as next_on' => $upcoming], 'held_on')
            ->with(['readings', 'meetings' => fn ($q) => $upcoming($q)->chronological()])
            ->orderBy('next_on')
            ->orderBy('id')
            ->paginate($perPage, pageName: 'proximas')
            ->withQueryString();

        return $page->through(function (Lesson $lesson) use ($today) {
            /** @var ClassMeeting $meeting */
            $meeting = $lesson->meetings->first();
            $meeting->setRelation('lesson', $lesson);

            return [
                'meeting_id' => $meeting->id,
                'held_on' => $meeting->held_on->toDateString(),
                'is_today' => $meeting->held_on->toDateString() === $today,
                'meetings_count' => $lesson->meetings->count(),
                'lesson' => [
                    'id' => $lesson->id,
                    'slug' => $lesson->slug,
                    'display_title' => $lesson->displayTitle(),
                    'bible_reference' => $lesson->bible_reference,
                    'status' => $lesson->status->value,
                ],
                'message' => $this->weeklyMessage->for($meeting),
            ];
        });
    }

    /**
     * Estudo em casa da lição da semana (null sem domingo pela frente).
     *
     * @return array{lesson_id: int, lesson_title: string, readings_total: int, readers: int, expected: int, rate: int|null, avg_days: float, days: array<int, int>}|null
     */
    public function currentStudy(CurrentLesson $week, AttendanceBook $book): ?array
    {
        $lesson = $week->isFallback ? null : $week->lesson;

        if ($lesson === null) {
            return null;
        }

        $firstSunday = ClassMeeting::query()->where('lesson_id', $lesson->id)->active()->min('held_on');
        $sunday = substr((string) ($firstSunday ?? ChurchCalendar::todayString()), 0, 10);

        return $this->homeStudy->forLessons([$lesson->id => $sunday], $book)[$lesson->id];
    }

    /**
     * Quem precisa de atenção (também usado na lista de Alunos).
     *
     * @param  array{readings_total: int, days: array<int, int>}|null  $study  estudo da lição atual
     * @return list<array{id: int, name: string, phone: string|null, reasons: list<string>, missed_in_a_row: int, last_present_on: string|null, last_read_on: string|null}>
     */
    public function attention(Classroom $classroom, AttendanceBook $book, ?array $study): array
    {
        return $this->needingAttention->for(
            $book,
            $this->homeStudy->lastReadOn($classroom, array_keys($book->since)),
            $study,
        );
    }

    /**
     * @return array{id: int, held_on: string, lesson: string|null, title: string|null, present: int, expected: int, visitors: int, rate: int|null}
     */
    private function sundayRow(ClassMeeting $meeting, AttendanceBook $book): array
    {
        return [
            'id' => $meeting->id,
            'held_on' => $meeting->held_on->toDateString(),
            'lesson' => $meeting->lesson?->displayTitle(),
            'title' => $meeting->title,
            ...$book->sunday($meeting),
        ];
    }

    private function finishedToday(CurrentLesson $week, string $today): bool
    {
        return ! $week->isFallback
            && $week->meeting?->status === MeetingStatus::Held
            && $week->meeting->held_on->toDateString() === $today;
    }

    /**
     * Aniversariantes do mês, pelo dia.
     *
     * @return list<array{id: int, name: string, phone: string|null, day: int, is_today: bool, turning: int}>
     */
    private function birthdays(AttendanceBook $book, int $month, int $day): array
    {
        $year = ChurchCalendar::today()->year;

        return array_values($book->students
            ->filter(fn (object $s) => $s->birth_date !== null && (int) substr($s->birth_date, 5, 2) === $month)
            ->map(fn (object $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'phone' => $s->phone,
                'day' => (int) substr((string) $s->birth_date, 8, 2),
                'is_today' => (int) substr((string) $s->birth_date, 8, 2) === $day,
                'turning' => $year - (int) substr((string) $s->birth_date, 0, 4),
            ])
            ->sortBy('day')
            ->all());
    }
}
