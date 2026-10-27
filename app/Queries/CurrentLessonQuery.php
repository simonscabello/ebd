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
 * É a lição atual da classe (classrooms.current_lesson_id): fica quantos
 * encontros precisar e só muda quando o professor conclui. O encontro diz
 * quando: o próximo (hoje incluído) com a lição atual, ou um encontro com
 * título antes dele (evento ou aula especial). Uma lição que já ocupou dois domingos está no "2º encontro".
 * Sem lição atual, só um evento aparece; a última lição não volta.
 */
class CurrentLessonQuery
{
    public function for(Classroom $classroom, ?User $user, ?CarbonInterface $today = null): CurrentLesson
    {
        $today ??= ChurchCalendar::today();
        $lessonId = $classroom->current_lesson_id;

        $meeting = ClassMeeting::query()
            ->whereBelongsTo($classroom)
            ->active()
            // Encontro com título é do professor (evento ou aula especial): vale como está.
            ->where(fn ($q) => $q
                ->whereNotNull('title')
                ->when($lessonId, fn ($q, int $id) => $q->orWhere('lesson_id', $id)))
            ->fromDate($today)
            ->chronological()
            ->with(['lesson.series', 'lesson.classroom'])
            ->first();

        // Sem encontro marcado ainda (a projeção roda de madrugada e nas ações), a lição vale igual.
        $lesson = $meeting !== null
            ? $meeting->lesson
            : ($lessonId !== null ? Lesson::query()->with(['series', 'classroom'])->find($lessonId) : null);

        if ($meeting === null && $lesson === null) {
            return CurrentLesson::none();
        }

        $visible = $lesson !== null && Gate::forUser($user)->allows('view', $lesson);

        [$index, $total] = $lesson && $meeting ? $this->position($lesson, $meeting) : [0, 0];

        $cancelled = $meeting === null ? collect() : ClassMeeting::query()
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
        );
    }

    /**
     * Últimas lições concluídas pela classe, da mais recente.
     *
     * @return Collection<int, Lesson>
     */
    public function recent(Classroom $classroom, ?User $user, int $limit = 3, ?int $exceptLessonId = null): Collection
    {
        return Lesson::query()
            ->visibleTo($user)
            ->whereBelongsTo($classroom)
            ->when($exceptLessonId, fn ($q, $id) => $q->whereKeyNot($id))
            ->whereNotNull('studied_at')
            ->with(['series', 'classroom'])
            ->orderByDesc('studied_at')
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
