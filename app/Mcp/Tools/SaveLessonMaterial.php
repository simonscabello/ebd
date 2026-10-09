<?php

namespace App\Mcp\Tools;

use App\Actions\Materials\StoreLessonMaterial;
use App\Actions\Materials\UpdateLessonMaterial;
use App\Concerns\LessonValidationRules;
use App\Mcp\Concerns\DescribesLessonParts;
use App\Mcp\Presenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class SaveLessonMaterial extends EbdTool
{
    use DescribesLessonParts;
    use LessonValidationRules;

    protected string $name = 'save_lesson_material';

    protected string $title = 'Salvar material da lição';

    protected string $description = 'Adiciona a um rascunho de lição um material com link (vídeo, áudio, link ou referência), ou edita um existente (material_id; campos omitidos ficam como estão). PDFs e arquivos são enviados pelo app; de materiais com arquivo, só título, descrição e público mudam por aqui.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'lesson_id' => $schema->integer()->description('Id do rascunho.')->required(),
            'material_id' => $schema->integer()->description('Id do material a editar (veja get_lesson). Omita para adicionar. O tipo não muda na edição.'),
            ...$this->materialProperties($schema),
        ];
    }

    public function handle(Request $request, StoreLessonMaterial $store, UpdateLessonMaterial $update): Response
    {
        $user = $this->actor($request);
        $lesson = $this->resolveLesson($user, $request->get('lesson_id'));
        $this->authorize($request, 'update', $lesson);
        $this->ensureDraft($lesson);

        $material = null;

        if ($request->get('material_id') !== null) {
            $material = $lesson->materials()->whereKey((int) $request->get('material_id'))->first()
                ?? throw ValidationException::withMessages(['material_id' => 'Material não encontrado nesta lição. Veja os ids com get_lesson.']);
        }

        $rules = $this->lessonLinkMaterialRules();

        if ($material !== null) {
            $this->keepCurrentValues($request, Arr::only(Presenter::material($material), ['title', 'description', 'audience', 'url']));

            // O tipo não muda depois de criado (mesma regra do app).
            $request->merge(['type' => $material->type->value]);

            if ($material->hasFile()) {
                // O arquivo continua o mesmo: só título, descrição e público mudam.
                $rules['type'] = ['required'];
                $rules['url'] = ['prohibited'];
            }
        }

        $data = array_intersect_key($request->validate([...$rules, 'lesson_id' => ['required', 'integer'], 'material_id' => ['nullable', 'integer']], [], [...$this->lessonPartAttributes(), 'title' => 'título']), $rules);

        $material = $this->auditedSave(
            $request,
            $material,
            $lesson->classroom_id,
            // UpdateLessonMaterial trata "principal" ausente como falso: mantém o atual.
            fn () => $material === null ? $store->handle($lesson, $data) : $update->handle($material, $data + ['is_primary' => $material->is_primary]),
        );

        return $this->json([
            'saved' => $request->get('material_id') !== null ? 'updated' : 'created',
            'material' => Presenter::material($material->refresh()),
        ]);
    }
}
