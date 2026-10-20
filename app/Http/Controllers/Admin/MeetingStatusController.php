<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Lessons\SyncLessonSchedule;
use App\Actions\Meetings\CancelMeeting;
use App\Actions\Meetings\ContinueLessonNextMeeting;
use App\Concerns\MeetingValidationRules;
use App\Enums\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassMeeting;
use App\Models\Lesson;
use App\Support\ChurchCalendar;
use App\Support\ProgramNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Ações do encontro: sem EBD (e desfazer), continua no próximo, teve aula.
 * Os avisos seguem o dia e o programa da classe ("domingo"/"EBD" na EBD).
 */
class MeetingStatusController extends Controller
{
    use MeetingValidationRules;

    public function cancel(Request $request, ClassMeeting $meeting, CancelMeeting $cancel): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $data = $request->validate($this->cancelMeetingRules());

        $leftover = $cancel->handle($meeting, $data['reason'] ?? null, (bool) ($data['shift'] ?? true));

        $program = app(ProgramNames::class)->shortFor($meeting->classroom_id);
        $this->toast("{$meeting->classroom->meeting_weekday->meetingName()} marcado como \"sem {$program}\".");
        $this->warnLeftover($meeting, $leftover);

        return back();
    }

    public function continue(ClassMeeting $meeting, ContinueLessonNextMeeting $continue): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $leftover = $continue->handle($meeting);

        $this->toast("A lição continua {$meeting->classroom->meeting_weekday->nextMeetingPhrase()}.");
        $this->warnLeftover($meeting, $leftover);

        return back();
    }

    public function held(ClassMeeting $meeting): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $weekday = $meeting->classroom->meeting_weekday;

        if ($meeting->held_on->toDateString() > ChurchCalendar::today()->toDateString()) {
            $this->toast("Este {$weekday->meetingNoun()} ainda não aconteceu.", 'error');

            return back();
        }

        $meeting->update(['status' => MeetingStatus::Held]);

        $this->toast("{$weekday->meetingName()} marcado como realizado.");

        return back();
    }

    /**
     * Desfaz o "sem EBD": o domingo volta a ser planejado, sem lição (ela foi
     * empurrada ou ficou sem data no cancelamento) e sem o motivo.
     */
    public function restore(ClassMeeting $meeting, SyncLessonSchedule $sync): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        if (! $meeting->isCancelled()) {
            return back();
        }

        $weekday = $meeting->classroom->meeting_weekday;

        if ($meeting->held_on->dayOfWeekIso !== $weekday->value) {
            throw ValidationException::withMessages(['meeting' => $weekday->meetingDayMessage()]);
        }

        $meeting->update(['status' => MeetingStatus::Planned, 'title' => null]);
        $sync->handle($meeting->classroom_id);

        $this->toast("O {$weekday->meetingNoun()} voltou para a agenda. Escolha a lição do dia.");

        return back();
    }

    private function warnLeftover(ClassMeeting $meeting, ?int $lessonId): void
    {
        if ($lessonId === null) {
            return;
        }

        $lesson = Lesson::query()->find($lessonId);

        if ($lesson !== null) {
            $weekday = $meeting->classroom->meeting_weekday;
            $this->toast("\"{$lesson->displayTitle()}\" ficou sem data. Escolha um {$weekday->meetingNoun()} para ela em {$weekday->meetingNamePlural()}.", 'warning');
        }
    }
}
