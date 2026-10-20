<?php

namespace App\Actions\Lessons;

use App\Enums\ContentAudience;
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

        // Publicar avisa a classe inteira: lição só com título (ou só com o
        // esqueleto "## I. / ### 1." do editor) não sai como "lição nova".
        if ($target === LessonStatus::Published && ! self::hasRealContent($lesson)) {
            throw ValidationException::withMessages([
                'status' => 'A lição ainda está vazia. Escreva o estudo (ou um complemento para os alunos) antes de publicar.',
            ]);
        }

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

    /**
     * Tem texto de estudo além dos títulos do esqueleto, um complemento para
     * os alunos com texto ou um material (PDF, link) para os alunos.
     */
    public static function hasRealContent(Lesson $lesson): bool
    {
        $text = preg_replace('/^\s*#{1,6}\s.*$/m', '', strip_tags((string) $lesson->content)) ?? '';

        if (preg_match('/[\p{L}\p{N}]/u', $text) === 1) {
            return true;
        }

        return $lesson->materials()->where('audience', ContentAudience::Student)->exists()
            || $lesson->blocks()->where('audience', ContentAudience::Student)->where(fn ($q) => $q->whereNotNull('body')->where('body', '<>', ''))->exists();
    }
}
