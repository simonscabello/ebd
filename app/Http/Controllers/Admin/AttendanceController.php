<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Meetings\FinishMeeting;
use App\Actions\Meetings\RecordAttendance;
use App\Http\Controllers\Controller;
use App\Models\ClassMeeting;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Chamada e "Encerrar aula" (Modo Domingo).
 */
class AttendanceController extends Controller
{
    public function update(Request $request, ClassMeeting $meeting, RecordAttendance $record): RedirectResponse
    {
        Gate::authorize('takeAttendance', $meeting);

        $data = $request->validate([
            'present' => ['present', 'array', 'max:500'],
            'present.*' => ['integer'],
            'visitors' => ['nullable', 'integer', 'min:0', 'max:500'],
        ]);

        $record->handle($meeting, $data['present'] ?? [], (int) ($data['visitors'] ?? 0), $request->user());

        return back();
    }

    public function finish(Request $request, ClassMeeting $meeting, FinishMeeting $finish): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $data = $request->validate([
            'continues' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $leftover = $finish->handle($meeting, (bool) $data['continues'], $data['notes'] ?? null);

        $this->toast($data['continues']
            ? 'Aula encerrada. A lição continua no próximo encontro.'
            : 'Aula encerrada. Até domingo!');

        if ($leftover !== null && ($lesson = Lesson::query()->find($leftover)) !== null) {
            $this->toast("\"{$lesson->displayTitle()}\" ficou sem data. Ajuste na agenda da classe.", 'warning');
        }

        return back();
    }
}
