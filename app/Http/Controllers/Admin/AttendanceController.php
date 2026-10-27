<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Meetings\FinishMeeting;
use App\Actions\Meetings\RecordAttendance;
use App\Concerns\MeetingValidationRules;
use App\Http\Controllers\Controller;
use App\Models\ClassMeeting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Chamada (Modo Domingo e página do domingo) e "Encerrar aula".
 */
class AttendanceController extends Controller
{
    use MeetingValidationRules;

    public function update(Request $request, ClassMeeting $meeting, RecordAttendance $record): RedirectResponse
    {
        Gate::authorize('takeAttendance', $meeting);

        $data = $request->validate($this->attendanceRules());

        $visitors = isset($data['visitors']) ? (int) $data['visitors'] : null;

        $record->handle($meeting, $data['present'] ?? [], $visitors, $request->user());

        return back();
    }

    public function finish(Request $request, ClassMeeting $meeting, FinishMeeting $finish): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $data = $request->validate($this->finishMeetingRules());

        $next = $finish->handle($meeting, (bool) $data['continues'], $data['notes'] ?? null);

        $weekday = $meeting->classroom->meeting_weekday;
        $this->toast(match (true) {
            (bool) $data['continues'] => "Aula encerrada. A lição continua {$weekday->nextMeetingPhrase()}.",
            $next !== null => "Aula encerrada. Próxima lição: {$next->displayTitle()}.",
            default => 'Aula encerrada. Escolha a próxima lição na página da classe.',
        });

        return back();
    }
}
