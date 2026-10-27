<?php

namespace App\Actions\Teaching;

use App\Actions\Meetings\SyncUpcomingMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;

/**
 * "Concluir e passar para a próxima": a lição atual vira estudada e a próxima
 * não estudada da série atual passa a ser a atual (a seguinte na ordem; sem
 * seguinte, a primeira que ficou para trás). Sem nenhuma, a classe fica sem
 * lição atual até o professor escolher ou concluir a série.
 *
 * Recebe a lição que a tela mostrava: clique duplo ou aba antiga não pulam
 * duas lições.
 */
class AdvanceLesson
{
    public function __construct(private readonly SyncUpcomingMeeting $syncMeeting) {}

    public function handle(Classroom $classroom, int $expectedLessonId): ?Lesson
    {
        return DB::transaction(function () use ($classroom, $expectedLessonId) {
            $locked = Classroom::query()->lockForUpdate()->findOrFail($classroom->id);

            if ($locked->current_lesson_id !== $expectedLessonId) {
                return $locked->currentLesson;
            }

            $current = Lesson::query()->find($expectedLessonId);
            $current?->forceFill(['studied_at' => now()])->save();

            $next = $this->next($locked, $current);

            $locked->forceFill([
                'current_lesson_id' => $next?->id,
                'current_lesson_started_at' => $next !== null ? now() : null,
            ])->save();

            $this->syncMeeting->handle($locked);

            return $next;
        });
    }

    private function next(Classroom $classroom, ?Lesson $current): ?Lesson
    {
        if ($classroom->current_series_id === null) {
            return null;
        }

        $pending = Lesson::query()
            ->where('series_id', $classroom->current_series_id)
            ->whereNull('studied_at')
            ->inSequence()
            ->get();

        if ($current?->series_id !== $classroom->current_series_id) {
            return $pending->first();
        }

        // Mesma ordem de inSequence: número (sem número no fim), depois id.
        $position = fn (Lesson $lesson) => [$lesson->number ?? PHP_INT_MAX, $lesson->id];

        return $pending->first(fn (Lesson $lesson) => $position($lesson) > $position($current)) ?? $pending->first();
    }
}
