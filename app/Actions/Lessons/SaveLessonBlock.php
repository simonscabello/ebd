<?php

namespace App\Actions\Lessons;

use App\Enums\ContentAudience;
use App\Enums\LessonBlockKind;
use App\Models\Lesson;
use App\Models\LessonBlock;

/**
 * Cria ou atualiza um bloco da lição e refaz o texto de busca.
 */
class SaveLessonBlock
{
    public function __construct(
        private readonly SyncLessonSearchText $syncSearch,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validados por LessonValidationRules::lessonBlockRules
     */
    public function handle(Lesson $lesson, array $data, ?LessonBlock $block = null): LessonBlock
    {
        $data = self::normalize($data);

        if ($block !== null) {
            $block->update($data);
        } else {
            $block = $lesson->blocks()->create($data);
        }

        $this->syncSearch->handle($lesson);

        return $block;
    }

    /**
     * Sem público informado, usa o padrão do tipo (roteiro = professor,
     * curiosidade = aluno...). Conteúdo do professor nunca é liberado em
     * "Minha semana".
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $kind = $data['kind'] instanceof LessonBlockKind ? $data['kind'] : LessonBlockKind::from((string) $data['kind']);
        $data['audience'] ??= $kind->defaultAudience()->value;

        if ($data['audience'] === ContentAudience::Teacher->value || $data['audience'] === ContentAudience::Teacher) {
            $data['drip_weekday'] = null;
        }

        return $data;
    }
}
