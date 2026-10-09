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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Ações do domingo: sem EBD (e desfazer), continua no próximo, teve aula.
 */
class MeetingStatusController extends Controller
{
    use MeetingValidationRules;

    public function cancel(Request $request, ClassMeeting $meeting, CancelMeeting $cancel): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $data = $request->validate($this->cancelMeetingRules());

        $leftover = $cancel->handle($meeting, $data['reason'] ?? null, (bool) ($data['shift'] ?? true));

        $this->toast('Domingo marcado como "sem EBD".');
        $this->warnLeftover($leftover);

        return back();
    }

    public function continue(ClassMeeting $meeting, ContinueLessonNextMeeting $continue): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $leftover = $continue->handle($meeting);

        $this->toast('A lição continua no próximo domingo.');
        $this->warnLeftover($leftover);

        return back();
    }

    public function held(ClassMeeting $meeting): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        if ($meeting->held_on->toDateString() > ChurchCalendar::today()->toDateString()) {
            $this->toast('Este domingo ainda não aconteceu.', 'error');

            return back();
        }

        $meeting->update(['status' => MeetingStatus::Held]);

        $this->toast('Domingo marcado como realizado.');

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

        $meeting->update(['status' => MeetingStatus::Planned, 'title' => null]);
        $sync->handle($meeting->classroom_id);

        $this->toast('O domingo voltou para a agenda. Escolha a lição do dia.');

        return back();
    }

    private function warnLeftover(?int $lessonId): void
    {
        if ($lessonId === null) {
            return;
        }

        $lesson = Lesson::query()->find($lessonId);

        if ($lesson !== null) {
            $this->toast("\"{$lesson->displayTitle()}\" ficou sem data. Escolha um domingo para ela em Domingos.", 'warning');
        }
    }
}
