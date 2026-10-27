<?php

namespace App\Actions\Teaching;

use App\Actions\Meetings\SyncUpcomingMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Queries\NextLessonsQuery;
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
    public function __construct(
        private readonly SyncUpcomingMeeting $syncMeeting,
        private readonly NextLessonsQuery $nextLessons,
    ) {}

    public function handle(Classroom $classroom, int $expectedLessonId): ?Lesson
    {
        return DB::transaction(function () use ($classroom, $expectedLessonId) {
            $locked = Classroom::query()->lockForUpdate()->findOrFail($classroom->id);

            if ($locked->current_lesson_id !== $expectedLessonId) {
                return $locked->currentLesson;
            }

            $current = Lesson::query()->find($expectedLessonId);
            $current?->forceFill(['studied_at' => now()])->save();

            $next = $this->nextLessons->for($locked, $current)->first();

            $locked->forceFill([
                'current_lesson_id' => $next?->id,
                'current_lesson_started_at' => $next !== null ? now() : null,
            ])->save();

            $this->syncMeeting->handle($locked);

            return $next;
        });
    }
}
