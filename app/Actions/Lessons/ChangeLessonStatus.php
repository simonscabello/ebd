<?php

namespace App\Actions\Lessons;

use App\Enums\LessonStatus;
use App\Events\LessonPublished;
use App\Models\Lesson;
use Illuminate\Validation\ValidationException;

/**
 * Publicar, despublicar, concluir e reabrir lições respeitando o ciclo de
 * vida definido em LessonStatus.
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

        if ($target === LessonStatus::Published && $lesson->scheduled_for === null) {
            throw ValidationException::withMessages([
                'scheduled_for' => 'Informe a data da aula antes de publicar a lição.',
            ]);
        }

        $wasDraft = $lesson->status === LessonStatus::Draft;

        $lesson->status = $target;

        match ($target) {
            LessonStatus::Published => $lesson->forceFill([
                'published_at' => $lesson->published_at ?? now(),
                'completed_at' => null,
            ]),
            LessonStatus::Completed => $lesson->forceFill(['completed_at' => now()]),
            LessonStatus::Draft => $lesson->forceFill(['published_at' => null, 'completed_at' => null]),
        };

        $lesson->save();

        if ($wasDraft && $target === LessonStatus::Published) {
            LessonPublished::dispatch($lesson);
        }

        return $lesson;
    }
}
