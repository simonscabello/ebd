<?php

namespace App\Actions\Lessons;

use App\Models\Lesson;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reordena materiais, perguntas, leituras ou blocos de uma lição.
 * Recebe a lista completa de IDs na nova ordem.
 */
class ReorderLessonItems
{
    public const RELATIONS = ['materials', 'questions', 'readings', 'blocks'];

    /**
     * @param  'materials'|'questions'|'readings'|'blocks'  $relation
     * @param  list<int>  $orderedIds
     */
    public function handle(Lesson $lesson, string $relation, array $orderedIds): void
    {
        $orderedIds = array_map('intval', $orderedIds);
        $currentIds = $lesson->{$relation}()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $sortedNew = $orderedIds;
        $sortedCurrent = $currentIds;
        sort($sortedNew);
        sort($sortedCurrent);

        if ($sortedNew !== $sortedCurrent) {
            throw ValidationException::withMessages([
                'ids' => 'A lista enviada não corresponde aos itens da lição.',
            ]);
        }

        DB::transaction(function () use ($lesson, $relation, $orderedIds) {
            foreach ($orderedIds as $index => $id) {
                $lesson->{$relation}()->whereKey($id)->update(['position' => $index + 1]);
            }
        });
    }
}
