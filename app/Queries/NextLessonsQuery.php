<?php

namespace App\Queries;

use App\Models\Classroom;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Collection;

/**
 * As próximas lições da classe: as não estudadas da série atual, a partir da
 * seguinte à lição atual na ordem da série; as que ficaram para trás vêm
 * depois. Sem série atual, nenhuma.
 */
class NextLessonsQuery
{
    /**
     * @return Collection<int, Lesson>
     */
    public function for(Classroom $classroom, ?Lesson $current, ?int $limit = null): Collection
    {
        if ($classroom->current_series_id === null) {
            return new Collection;
        }

        $pending = Lesson::query()
            ->where('series_id', $classroom->current_series_id)
            ->whereNull('studied_at')
            ->when($current, fn ($q, Lesson $lesson) => $q->whereKeyNot($lesson->id))
            ->inSequence()
            ->get();

        if ($current !== null && $current->series_id === $classroom->current_series_id) {
            // Mesma ordem de inSequence: número (sem número no fim), depois id.
            $position = fn (Lesson $lesson) => [$lesson->number ?? PHP_INT_MAX, $lesson->id];
            [$after, $before] = $pending->partition(fn (Lesson $lesson) => $position($lesson) > $position($current));
            $pending = $after->concat($before)->values();
        }

        return $limit === null ? $pending : $pending->take($limit)->values();
    }
}
