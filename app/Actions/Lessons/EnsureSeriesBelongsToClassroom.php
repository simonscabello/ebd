<?php

namespace App\Actions\Lessons;

use App\Models\Classroom;
use App\Models\Series;
use Illuminate\Validation\ValidationException;

/**
 * Invariante: a série de uma lição precisa ser da mesma classe da lição.
 */
class EnsureSeriesBelongsToClassroom
{
    public function handle(Classroom $classroom, int|string|null $seriesId): ?int
    {
        if (blank($seriesId)) {
            return null;
        }

        $belongs = Series::query()
            ->whereKey($seriesId)
            ->where('classroom_id', $classroom->id)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'series_id' => 'A série escolhida não pertence a esta classe.',
            ]);
        }

        return (int) $seriesId;
    }
}
