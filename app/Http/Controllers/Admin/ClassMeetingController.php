<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Lessons\SyncLessonSchedule;
use App\Actions\Meetings\PlanMeetings;
use App\Actions\Meetings\SaveMeeting;
use App\Concerns\MeetingValidationRules;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MeetingRequest;
use App\Http\Resources\ClassMeetingResource;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\SeriesResource;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Queries\AttendanceBookQuery;
use App\Queries\Data\AttendanceBook;
use App\Queries\Data\Period;
use App\Support\ChurchCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Domingos da classe: a lista (próximos e anteriores) e a página de cada
 * domingo, com a lição, a chamada e "onde paramos".
 */
class ClassMeetingController extends Controller
{
    use MeetingValidationRules;

    /** Quantas semanas para trás a lista mostra antes de "ver todos". */
    private const PAST_WEEKS = 12;

    public function index(Request $request, Classroom $classroom, AttendanceBookQuery $books): Response
    {
        Gate::authorize('manageContent', $classroom);

        $today = ChurchCalendar::today();
        $todayString = $today->toDateString();
        $showAll = $request->boolean('todos');
        $from = $showAll ? null : $today->subWeeks(self::PAST_WEEKS)->toDateString();

        $meetings = $classroom->meetings()
            ->when($from, fn ($query, string $date) => $query->whereDate('held_on', '>=', $date))
            ->chronological()
            ->with('lesson')
            ->get();

        $book = $books->for($classroom, new Period($from, $todayString, ''));
        $positions = $this->positions($classroom, $meetings);
        $row = fn (ClassMeeting $m) => $this->row($m, $book, $positions, $todayString);

        [$upcoming, $past] = $meetings->partition(fn (ClassMeeting $m) => $m->held_on->toDateString() >= $todayString);

        return Inertia::render('admin/classrooms/meetings/index', [
            'classroom' => ClassroomResource::make($classroom),
            'today' => $todayString,
            'nextSunday' => ChurchCalendar::nextSunday()->toDateString(),
            'upcoming' => $upcoming->map($row)->values(),
            'past' => $past->reverse()->map($row)->values(),
            'showingAll' => $showAll,
            'hasOlder' => ! $showAll && $classroom->meetings()->whereDate('held_on', '<', (string) $from)->exists(),
            'lessons' => $this->lessonOptions($classroom),
            'series' => SeriesResource::collection($classroom->series()->orderByDesc('starts_on')->get()),
        ]);
    }

    public function show(Request $request, Classroom $classroom, ClassMeeting $meeting, AttendanceBookQuery $books): Response
    {
        Gate::authorize('update', $meeting);

        $today = ChurchCalendar::todayString();
        $date = $meeting->held_on->toDateString();
        $meeting->load('lesson');
        $canTake = ! $meeting->isCancelled() && $date <= $today;
        $book = $books->for($classroom, new Period($date, $date, ''));
        $position = $this->positions($classroom, collect([$meeting]))[$meeting->id] ?? null;

        $previousId = $classroom->meetings()->whereDate('held_on', '<', $date)->orderByDesc('held_on')->value('id');
        $nextId = $classroom->meetings()->whereDate('held_on', '>', $date)->orderBy('held_on')->value('id');

        return Inertia::render('admin/classrooms/meetings/show', [
            'classroom' => ClassroomResource::make($classroom),
            'today' => $today,
            'meeting' => fn () => [
                ...ClassMeetingResource::make($meeting)->withNotes()->resolve($request),
                'is_today' => $date === $today,
                'is_future' => $date > $today,
                'can_take_attendance' => $canTake,
                'position' => $position,
                'previous_id' => $previousId,
                'next_id' => $nextId,
            ],
            'summary' => fn () => $meeting->hasAttendance() ? $book->sunday($meeting) : null,
            'attendance' => fn () => $canTake ? [
                'roster' => $book->students
                    ->map(fn (object $s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        // Entrou na classe depois deste domingo: não conta como falta.
                        'joined_after' => ! $book->isExpected($s->id, $date),
                    ])
                    // Ordenação estável: mantém a ordem alfabética do banco.
                    ->sortBy(fn (array $s) => $s['joined_after'] ? 1 : 0)
                    ->values(),
                'present' => $meeting->attendances()->pluck('user_id')
                    ->map(fn ($id) => (int) $id)
                    ->intersect($book->students->pluck('id'))
                    ->values(),
                'visitors' => $meeting->visitors_count,
            ] : null,
            'lessons' => fn () => $this->lessonOptions($classroom),
        ]);
    }

    public function store(MeetingRequest $request, Classroom $classroom, SaveMeeting $save): RedirectResponse
    {
        $save->handle($classroom, $request->validated());

        $this->toast('Domingo adicionado.');

        return back();
    }

    public function update(MeetingRequest $request, ClassMeeting $meeting, SaveMeeting $save): RedirectResponse
    {
        $save->handle($meeting->classroom, $request->validated(), $meeting);

        $this->toast('Domingo atualizado.');

        return back();
    }

    public function destroy(ClassMeeting $meeting, SyncLessonSchedule $sync): RedirectResponse
    {
        Gate::authorize('delete', $meeting);

        if ($meeting->hasAttendance()) {
            $this->toast('Este domingo tem chamada registrada e não pode ser removido.', 'error');

            return back();
        }

        $meeting->delete();
        $sync->handle($meeting->classroom_id);

        $this->toast('Domingo removido.');

        // A página do domingo removido deixa de existir: volta para a lista.
        return to_route('admin.classrooms.meetings.index', $meeting->classroom);
    }

    public function plan(Request $request, Classroom $classroom, PlanMeetings $plan): RedirectResponse
    {
        Gate::authorize('manageContent', $classroom);

        $data = $request->validate($this->meetingPlanRules($classroom, $request->input('from')), [], $this->meetingAttributes());

        $result = $plan->handle(
            $classroom,
            CarbonImmutable::parse($data['from']),
            CarbonImmutable::parse($data['to']),
            isset($data['series_id']) ? Series::query()->findOrFail((int) $data['series_id']) : null,
        );

        $this->toast("{$result['created']} domingo(s) adicionados; {$result['assigned']} lição(ões) distribuídas.");

        return back();
    }

    /**
     * @param  array<int, array{index: int, total: int}>  $positions
     * @return array<string, mixed>
     */
    private function row(ClassMeeting $meeting, AttendanceBook $book, array $positions, string $today): array
    {
        $date = $meeting->held_on->toDateString();

        return [
            'id' => $meeting->id,
            'held_on' => $date,
            'status' => $meeting->status->value,
            'status_label' => $meeting->status->label(),
            'title' => $meeting->title,
            'lesson' => $meeting->lesson ? [
                'id' => $meeting->lesson->id,
                'display_title' => $meeting->lesson->displayTitle(),
                'status' => $meeting->lesson->status->value,
            ] : null,
            'has_attendance' => $meeting->hasAttendance(),
            'summary' => $meeting->hasAttendance() ? $book->sunday($meeting) : null,
            'position' => $positions[$meeting->id] ?? null,
            'is_today' => $date === $today,
            'awaiting_confirmation' => $meeting->status->value === 'planned' && $date < $today,
            'has_notes' => filled($meeting->notes),
        ];
    }

    /**
     * "Domingo 2 de 2": posição de cada encontro entre os da mesma lição.
     *
     * @param  Collection<int, ClassMeeting>  $meetings
     * @return array<int, array{index: int, total: int}>
     */
    private function positions(Classroom $classroom, Collection $meetings): array
    {
        $lessonIds = $meetings->pluck('lesson_id')->filter()->unique()->values();

        if ($lessonIds->isEmpty()) {
            return [];
        }

        $positions = [];

        ClassMeeting::query()
            ->whereBelongsTo($classroom)
            ->whereIn('lesson_id', $lessonIds)
            ->active()
            ->chronological()
            ->get(['id', 'lesson_id'])
            ->groupBy('lesson_id')
            ->each(function (Collection $sameLesson) use (&$positions) {
                foreach ($sameLesson->values() as $index => $meeting) {
                    $positions[$meeting->id] = ['index' => $index + 1, 'total' => $sameLesson->count()];
                }
            });

        return $positions;
    }

    /**
     * @return Collection<int, array{id: int, label: string, status: 'draft'|'published', series_id: int|null}>
     */
    private function lessonOptions(Classroom $classroom): Collection
    {
        return Lesson::query()
            ->whereBelongsTo($classroom)
            ->orderByRaw('number IS NULL, number')
            ->orderBy('title')
            ->get(['id', 'number', 'title', 'status', 'series_id'])
            ->map(fn (Lesson $l) => [
                'id' => $l->id,
                'label' => $l->displayTitle(),
                'status' => $l->status->value,
                'series_id' => $l->series_id,
            ]);
    }
}
