<?php

namespace App\Mcp\Tools;

use App\Actions\Lessons\SaveLessonBlock;
use App\Concerns\LessonValidationRules;
use App\Mcp\Concerns\DescribesLessonParts;
use App\Mcp\Presenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class SaveLessonBlockTool extends EbdTool
{
    use DescribesLessonParts;
    use LessonValidationRules;

    protected string $name = 'save_lesson_block';

    protected string $title = 'Salvar bloco da lição';

    protected string $description = 'Adiciona um bloco a um rascunho de lição (roteiro, contexto, teologia, curiosidade, aplicação...) ou edita um bloco existente (block_id; campos omitidos ficam como estão). Os blocos do professor não aparecem para os alunos.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'lesson_id' => $schema->integer()->description('Id do rascunho.')->required(),
            'block_id' => $schema->integer()->description('Id do bloco a editar (veja get_lesson). Omita para adicionar.'),
            ...$this->blockProperties($schema),
        ];
    }

    public function handle(Request $request, SaveLessonBlock $save): Response
    {
        $user = $this->actor($request);
        $lesson = $this->resolveLesson($user, $request->get('lesson_id'));
        $this->authorize($request, 'update', $lesson);
        $this->ensureDraft($lesson);

        $block = null;

        if ($request->get('block_id') !== null) {
            $block = $lesson->blocks()->whereKey((int) $request->get('block_id'))->first()
                ?? throw ValidationException::withMessages(['block_id' => 'Bloco não encontrado nesta lição. Veja os ids com get_lesson.']);

            $this->keepCurrentValues($request, Arr::only(Presenter::block($block), ['kind', 'audience', 'title', 'body', 'drip_weekday']));
        }

        $rules = $this->lessonBlockRules();
        $data = $request->validate([...$rules, 'lesson_id' => ['required', 'integer'], 'block_id' => ['nullable', 'integer']], [], [...$this->lessonPartAttributes(), 'title' => 'título']);

        $block = $this->auditedSave(
            $request,
            $block,
            $lesson->classroom_id,
            fn () => $save->handle($lesson, array_intersect_key($data, $rules), $block),
        );

        return $this->json([
            'saved' => $request->get('block_id') !== null ? 'updated' : 'created',
            'block' => Presenter::block($block->refresh()),
            'edit_url' => route('admin.lessons.edit', $lesson),
        ]);
    }
}
