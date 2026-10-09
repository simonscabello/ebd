<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Meetings\FinishMeeting;
use App\Actions\Meetings\RecordAttendance;
use App\Concerns\MeetingValidationRules;
use App\Http\Controllers\Controller;
use App\Models\ClassMeeting;
use App\Models\Lesson;
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

        $leftover = $finish->handle($meeting, (bool) $data['continues'], $data['notes'] ?? null);

        $this->toast($data['continues']
            ? 'Aula encerrada. A lição continua no próximo domingo.'
            : 'Aula encerrada. Até domingo!');

        if ($leftover !== null && ($lesson = Lesson::query()->find($leftover)) !== null) {
            $this->toast("\"{$lesson->displayTitle()}\" ficou sem data. Ajuste em Domingos.", 'warning');
        }

        return back();
    }
}
