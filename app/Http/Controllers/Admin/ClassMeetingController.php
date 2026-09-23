<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Lessons\SyncLessonSchedule;
use App\Actions\Meetings\PlanMeetings;
use App\Actions\Meetings\SaveMeeting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MeetingRequest;
use App\Http\Resources\ClassMeetingResource;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\SeriesResource;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Support\ChurchCalendar;
use App\Support\WeeklyMessage;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Agenda da classe: os domingos, qual lição cai em cada um, domingos sem EBD.
 */
class ClassMeetingController extends Controller
{
    public function index(Request $request, Classroom $classroom, WeeklyMessage $weeklyMessage): Response
    {
        Gate::authorize('manageContent', $classroom);

        $today = ChurchCalendar::today();

        // Janela padrão: últimas 6 semanas e tudo que vem pela frente.
        $meetings = $classroom->meetings()
            ->whereDate('held_on', '>=', $today->subWeeks(6)->toDateString())
            ->chronological()
            ->with('lesson')
            ->get();

        $next = $meetings->first(fn (ClassMeeting $m) => ! $m->isCancelled() && $m->held_on->gte($today));

        return Inertia::render('admin/classrooms/agenda', [
            'classroom' => ClassroomResource::make($classroom),
            'meetings' => $meetings->map(fn (ClassMeeting $m) => ClassMeetingResource::make($m)->withNotes()->resolve($request)),
            'lessons' => Lesson::query()
                ->whereBelongsTo($classroom)
                ->orderByRaw('number IS NULL, number')
                ->orderBy('title')
                ->get(['id', 'number', 'title', 'status', 'series_id'])
                ->map(fn (Lesson $l) => [
                    'id' => $l->id,
                    'label' => $l->displayTitle(),
                    'status' => $l->status->value,
                    'series_id' => $l->series_id,
                ]),
            'series' => SeriesResource::collection($classroom->series()->orderByDesc('starts_on')->get()),
            'today' => $today->toDateString(),
            'nextSunday' => ChurchCalendar::nextSunday()->toDateString(),
            'weeklyMessage' => $next?->lesson ? $weeklyMessage->for($next) : null,
        ]);
    }

    public function store(MeetingRequest $request, Classroom $classroom, SaveMeeting $save): RedirectResponse
    {
        $save->handle($classroom, $request->validated());

        $this->toast('Encontro adicionado à agenda.');

        return back();
    }

    public function update(MeetingRequest $request, ClassMeeting $meeting, SaveMeeting $save): RedirectResponse
    {
        $save->handle($meeting->classroom, $request->validated(), $meeting);

        $this->toast('Encontro atualizado.');

        return back();
    }

    public function destroy(ClassMeeting $meeting, SyncLessonSchedule $sync): RedirectResponse
    {
        Gate::authorize('delete', $meeting);

        if ($meeting->hasAttendance()) {
            $this->toast('Este encontro tem chamada registrada e não pode ser removido.', 'error');

            return back();
        }

        $meeting->delete();
        $sync->handle($meeting->classroom_id);

        $this->toast('Encontro removido da agenda.');

        return back();
    }

    public function plan(Request $request, Classroom $classroom, PlanMeetings $plan): RedirectResponse
    {
        Gate::authorize('manageContent', $classroom);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:'.CarbonImmutable::parse((string) $request->input('from'))->addYear()->toDateString()],
            'series_id' => ['nullable', 'integer', Rule::exists('series', 'id')->where('classroom_id', $classroom->id)],
        ], [], ['from' => 'início', 'to' => 'fim', 'series_id' => 'série']);

        $result = $plan->handle(
            $classroom,
            CarbonImmutable::parse($data['from']),
            CarbonImmutable::parse($data['to']),
            isset($data['series_id']) ? Series::query()->findOrFail((int) $data['series_id']) : null,
        );

        $this->toast("{$result['created']} domingo(s) adicionados à agenda; {$result['assigned']} lição(ões) distribuídas.");

        return back();
    }
}
