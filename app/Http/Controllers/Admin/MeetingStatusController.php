<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Meetings\CancelMeeting;
use App\Actions\Meetings\SyncUpcomingMeeting;
use App\Concerns\MeetingValidationRules;
use App\Enums\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassMeeting;
use App\Support\ChurchCalendar;
use App\Support\ProgramNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Ações do encontro: sem EBD (e desfazer), teve aula. A lição atual segue
 * para o próximo encontro em aberto.
 * Os avisos seguem o dia e o programa da classe ("domingo"/"EBD" na EBD).
 */
class MeetingStatusController extends Controller
{
    use MeetingValidationRules;

    public function cancel(Request $request, ClassMeeting $meeting, CancelMeeting $cancel): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $data = $request->validate($this->cancelMeetingRules());

        $cancel->handle($meeting, $data['reason'] ?? null);

        $program = app(ProgramNames::class)->shortFor($meeting->classroom_id);
        $this->toast("{$meeting->classroom->meeting_weekday->meetingName()} marcado como \"sem {$program}\".");

        return back();
    }

    public function held(ClassMeeting $meeting, SyncUpcomingMeeting $sync): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $weekday = $meeting->classroom->meeting_weekday;

        if ($meeting->held_on->toDateString() > ChurchCalendar::today()->toDateString()) {
            $this->toast("Este {$weekday->meetingNoun()} ainda não aconteceu.", 'error');

            return back();
        }

        $meeting->update(['status' => MeetingStatus::Held]);
        $sync->handle($meeting->classroom);

        $this->toast("{$weekday->meetingName()} marcado como realizado.");

        return back();
    }

    /**
     * Desfaz o "sem EBD": o domingo volta a ser planejado, sem o motivo, e
     * recebe a lição atual se for o próximo.
     */
    public function restore(ClassMeeting $meeting, SyncUpcomingMeeting $sync): RedirectResponse
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
        $sync->handle($meeting->classroom);

        $this->toast("O {$weekday->meetingNoun()} voltou para a agenda.");

        return back();
    }
}
