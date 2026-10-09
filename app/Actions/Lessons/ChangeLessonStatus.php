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
    /** Aparelhos avisados pelo push na última publicação (0 se não houve envio). */
    public int $notifiedDevices = 0;

    /** O aviso da publicação falhou (o erro foi reportado; a publicação seguiu). */
    public bool $notificationFailed = false;

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

        $this->notifiedDevices = 0;
        $this->notificationFailed = false;

        if ($wasDraft && $target === LessonStatus::Published) {
            // Os listeners devolvem quantos aparelhos avisaram, ou null se
            // falharam (nada, com Event::fake).
            $results = is_array($results = LessonPublished::dispatch($lesson)) ? $results : [];
            $this->notifiedDevices = (int) array_sum(array_filter($results, 'is_int'));
            $this->notificationFailed = in_array(null, $results, true);
        }

        return $lesson;
    }
}
