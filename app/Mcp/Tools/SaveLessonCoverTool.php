<?php

namespace App\Mcp\Tools;

use App\Actions\Lessons\SaveLessonCover;
use App\Models\Lesson;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Capa da lição. Diferente do resto do conteúdo, vale também para lição já
 * publicada: trocar a capa não muda o estudo nem avisa a classe.
 */
class SaveLessonCoverTool extends EbdTool
{
    protected string $name = 'save_lesson_cover';

    protected string $title = 'Salvar capa da lição';

    protected string $description = 'Define ou troca a capa de uma lição (rascunho ou publicada): uma imagem JPEG, PNG ou WebP de pelo menos 600 × 600 px, em base64. Ela vira um JPEG quadrado de 1400 px (recorte no centro) e aparece nos cards, no episódio do podcast e na prévia do link. Com remove=true, tira a capa. Mostre a imagem à pessoa e confirme antes de salvar.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'lesson_id' => $schema->integer()->description('Id da lição.')->required(),
            'image_base64' => $schema->string()->description('Conteúdo do arquivo em base64 (sem o prefixo data:). Até 8 MB.'),
            'remove' => $schema->boolean()->description('true para tirar a capa atual.'),
        ];
    }

    public function handle(Request $request, SaveLessonCover $save): Response
    {
        $user = $this->actor($request);
        $data = $request->validate([
            'lesson_id' => ['required', 'integer'],
            'image_base64' => ['required_without:remove', 'nullable', 'string', 'max:'.(int) ceil(config('ebd.covers.max_upload_kb') * 1024 * 4 / 3)],
            'remove' => ['nullable', 'boolean'],
        ], [], ['image_base64' => 'imagem']);

        $lesson = $this->resolveLesson($user, $data['lesson_id']);
        $this->authorize($request, 'update', $lesson);

        $remove = (bool) $request->get('remove', false);
        $bytes = $remove ? null : base64_decode((string) $data['image_base64'], true);

        if ($bytes === false) {
            throw ValidationException::withMessages(['image_base64' => 'A imagem não está em base64 válido.']);
        }

        $this->audited(
            $request,
            $lesson,
            $lesson->classroom_id,
            fn () => $bytes === null ? $save->remove($lesson) : $save->handle($lesson, $bytes, 'image_base64'),
            fn (Lesson $l): array => ['has_cover' => $l->hasCover(), 'cover_updated_at' => $l->cover_updated_at?->toIso8601String()],
        );

        return $this->json([
            'saved' => $bytes === null ? 'removed' : 'updated',
            'lesson' => $lesson->displayTitle(),
            'cover_url' => $lesson->coverUrl(),
            'edit_url' => route('admin.lessons.edit', $lesson),
        ]);
    }
}
