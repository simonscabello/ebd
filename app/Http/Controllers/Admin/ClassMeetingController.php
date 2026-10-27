<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Meetings\SaveMeeting;
use App\Actions\Meetings\SyncUpcomingMeeting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MeetingRequest;
use App\Http\Resources\ClassMeetingResource;
use App\Http\Resources\ClassroomResource;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Queries\AttendanceBookQuery;
use App\Queries\Data\Period;
use App\Queries\MeetingRows;
use App\Support\ChurchCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Domingos da classe: a lista (próximo e anteriores) e a página de cada
 * domingo, com a lição, a chamada e "onde paramos". Só o próximo encontro
 * existe adiantado (SyncUpcomingMeeting); domingo sem aula é marcado como
 * "sem EBD", não removido.
 */
class ClassMeetingController extends Controller
{
    /** Quantas semanas para trás a lista mostra antes de "ver todos". */
    private const PAST_WEEKS = 12;

    public function index(Request $request, Classroom $classroom, AttendanceBookQuery $books, SyncUpcomingMeeting $sync, MeetingRows $rows): Response
    {
        Gate::authorize('manageContent', $classroom);

        $sync->handle($classroom);

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

        [$upcoming, $past] = $meetings->partition(fn (ClassMeeting $m) => $m->held_on->toDateString() >= $todayString);

        return Inertia::render('admin/classrooms/meetings/index', [
            'classroom' => ClassroomResource::make($classroom),
            'today' => $todayString,
            'upcoming' => $rows->for($classroom, $upcoming, $book, $todayString),
            'past' => $rows->for($classroom, $past->reverse(), $book, $todayString),
            'showingAll' => $showAll,
            'hasOlder' => ! $showAll && $classroom->meetings()->whereDate('held_on', '<', (string) $from)->exists(),
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
        $position = app(MeetingRows::class)->positions($classroom, collect([$meeting]))[$meeting->id] ?? null;

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

    public function update(MeetingRequest $request, ClassMeeting $meeting, SaveMeeting $save): RedirectResponse
    {
        $save->handle($meeting->classroom, $request->validated(), $meeting);

        $this->toast("{$meeting->classroom->meeting_weekday->meetingName()} atualizado.");

        return back();
    }

    /**
     * "Fazer chamada": abre o encontro de hoje (criado com a lição atual, se
     * faltar) ou, fora do dia de encontro, o último que já aconteceu.
     */
    public function today(Classroom $classroom, SyncUpcomingMeeting $sync): RedirectResponse
    {
        Gate::authorize('manageContent', $classroom);
        $classroom->ensureHoldsMeetings('meeting');

        $today = ChurchCalendar::today();

        if ($today->dayOfWeekIso === $classroom->meeting_weekday->value) {
            $meeting = ClassMeeting::query()->firstOrCreate(
                ['classroom_id' => $classroom->id, 'held_on' => $today->toDateString()],
                ['lesson_id' => $classroom->current_lesson_id],
            );
            $sync->handle($classroom);
        } else {
            $meeting = $classroom->meetings()->active()
                ->whereDate('held_on', '<=', $today->toDateString())
                ->orderByDesc('held_on')
                ->first();
        }

        if ($meeting === null) {
            $this->toast("A chamada abre no dia do {$classroom->meeting_weekday->meetingNoun()}.", 'error');

            return back();
        }

        return redirect()->route('admin.classrooms.meetings.show', [$classroom, $meeting]);
    }

    /**
     * @return Collection<int, array{id: int, label: string, status: 'draft'|'published', series_id: int|null, series_title: string|null}>
     */
    private function lessonOptions(Classroom $classroom): Collection
    {
        return Lesson::query()
            ->whereBelongsTo($classroom)
            ->orderByRaw('number IS NULL, number')
            ->orderBy('title')
            ->with('series:id,title')
            ->get(['id', 'number', 'title', 'status', 'series_id'])
            ->map(fn (Lesson $l) => [
                'id' => $l->id,
                'label' => $l->displayTitle(),
                'status' => $l->status->value,
                'series_id' => $l->series_id,
                'series_title' => $l->series?->title,
            ]);
    }
}
