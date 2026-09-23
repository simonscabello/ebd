<?php

namespace App\Queries;

use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use App\Queries\Data\CurrentLesson;
use App\Support\ChurchCalendar;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * "O que eu preciso estudar para o próximo domingo?"
 *
 * A lição da semana é a do próximo encontro (hoje incluído) que não foi
 * cancelado. Uma lição que ocupa dois domingos tem dois encontros ("2 de 2").
 * Sem encontro futuro, vale a do último encontro realizado.
 */
class CurrentLessonQuery
{
    public function for(Classroom $classroom, ?User $user, ?CarbonInterface $today = null): CurrentLesson
    {
        $today ??= ChurchCalendar::today();

        $meeting = ClassMeeting::query()
            ->whereBelongsTo($classroom)
            ->active()
            ->fromDate($today)
            ->chronological()
            ->with(['lesson.series', 'lesson.classroom'])
            ->first();

        $isFallback = false;

        if ($meeting === null) {
            $meeting = ClassMeeting::query()
                ->whereBelongsTo($classroom)
                ->where('status', MeetingStatus::Held)
                ->whereNotNull('lesson_id')
                ->whereDate('held_on', '<', $today->toDateString())
                ->orderByDesc('held_on')
                ->with(['lesson.series', 'lesson.classroom'])
                ->first();
            $isFallback = $meeting !== null;
        }

        if ($meeting === null) {
            return CurrentLesson::none();
        }

        $lesson = $meeting->lesson;
        $visible = $lesson !== null && Gate::forUser($user)->allows('view', $lesson);

        [$index, $total] = $lesson ? $this->position($lesson, $meeting) : [0, 0];

        $cancelled = $isFallback ? collect() : ClassMeeting::query()
            ->whereBelongsTo($classroom)
            ->where('status', MeetingStatus::Cancelled)
            ->fromDate($today)
            ->whereDate('held_on', '<', $meeting->held_on->toDateString())
            ->chronological()
            ->get();

        return new CurrentLesson(
            meeting: $meeting,
            lesson: $visible ? $lesson : null,
            preparing: $lesson !== null && ! $visible && ! $lesson->status->isVisible(),
            meetingIndex: $index,
            meetingTotal: $total,
            cancelledBefore: $cancelled,
            isFallback: $isFallback,
        );
    }

    /**
     * Últimas lições já estudadas (encontros realizados), sem repetir lição.
     *
     * @return Collection<int, Lesson>
     */
    public function recent(Classroom $classroom, ?User $user, int $limit = 3, ?int $exceptLessonId = null): Collection
    {
        $today = ChurchCalendar::today()->toDateString();

        return Lesson::query()
            ->visibleTo($user)
            ->whereBelongsTo($classroom)
            ->when($exceptLessonId, fn ($q, $id) => $q->whereKeyNot($id))
            ->whereHas('meetings', fn ($q) => $q->where('status', MeetingStatus::Held)->whereDate('held_on', '<', $today))
            ->withMax(['meetings as last_held_on' => fn ($q) => $q->where('status', MeetingStatus::Held)], 'held_on')
            ->with(['series', 'classroom'])
            ->orderByDesc('last_held_on')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{int, int}
     */
    private function position(Lesson $lesson, ClassMeeting $meeting): array
    {
        $dates = ClassMeeting::query()
            ->where('lesson_id', $lesson->id)
            ->where('classroom_id', $meeting->classroom_id)
            ->active()
            ->chronological()
            ->pluck('held_on')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $index = array_search($meeting->held_on->toDateString(), $dates, true);

        return [is_int($index) ? $index + 1 : 1, max(count($dates), 1)];
    }
}
