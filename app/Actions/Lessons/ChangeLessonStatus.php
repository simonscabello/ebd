<?php

namespace App\Actions\Lessons;

use App\Enums\LessonStatus;
use App\Events\LessonPublished;
use App\Models\Lesson;
use Illuminate\Validation\ValidationException;

/**
 * Publicar e despublicar lições respeitando o ciclo definido em LessonStatus.
 * A data não é exigida: ela vem dos encontros (agenda da classe).
 */
class ChangeLessonStatus
{
    public function handle(Lesson $lesson, LessonStatus $target): Lesson
    {
        if (! $lesson->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Não é possível mudar de \"{$lesson->status->label()}\" para \"{$target->label()}\".",
            ]);
        }

        $wasDraft = $lesson->status === LessonStatus::Draft;

        $lesson->status = $target;

        match ($target) {
            LessonStatus::Published => $lesson->forceFill(['published_at' => $lesson->published_at ?? now()]),
            LessonStatus::Draft => $lesson->forceFill(['published_at' => null]),
        };

        $lesson->save();

        if ($wasDraft && $target === LessonStatus::Published) {
            LessonPublished::dispatch($lesson);
        }

        return $lesson;
    }
}
