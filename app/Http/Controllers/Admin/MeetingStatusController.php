<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Meetings\CancelMeeting;
use App\Actions\Meetings\ContinueLessonNextMeeting;
use App\Enums\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassMeeting;
use App\Models\Lesson;
use App\Support\ChurchCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Ações rápidas da agenda: sem EBD, continua no próximo, realizado.
 */
class MeetingStatusController extends Controller
{
    public function cancel(Request $request, ClassMeeting $meeting, CancelMeeting $cancel): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:120'],
            'shift' => ['boolean'],
        ]);

        $leftover = $cancel->handle($meeting, $data['reason'] ?? null, (bool) ($data['shift'] ?? true));

        $this->toast('Domingo marcado como "sem EBD".');
        $this->warnLeftover($leftover);

        return back();
    }

    public function continue(ClassMeeting $meeting, ContinueLessonNextMeeting $continue): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $leftover = $continue->handle($meeting);

        $this->toast('A lição continua no próximo encontro.');
        $this->warnLeftover($leftover);

        return back();
    }

    public function held(ClassMeeting $meeting): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        if ($meeting->held_on->toDateString() > ChurchCalendar::today()->toDateString()) {
            $this->toast('Este encontro ainda não aconteceu.', 'error');

            return back();
        }

        $meeting->update(['status' => MeetingStatus::Held]);

        $this->toast('Encontro marcado como realizado.');

        return back();
    }

    private function warnLeftover(?int $lessonId): void
    {
        if ($lessonId === null) {
            return;
        }

        $lesson = Lesson::query()->find($lessonId);

        if ($lesson !== null) {
            $this->toast("\"{$lesson->displayTitle()}\" ficou sem data. Adicione um domingo na agenda.", 'warning');
        }
    }
}
