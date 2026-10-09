<?php

namespace App\Mcp\Tools;

use App\Actions\Lessons\SyncLessonSearchText;
use App\Actions\Materials\DeleteLessonMaterial;
use App\Models\LessonMaterial;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class RemoveLessonItem extends EbdTool
{
    protected string $name = 'remove_lesson_item';

    protected string $title = 'Remover item da lição';

    protected string $description = 'Remove um bloco, uma leitura ou um material com link de um rascunho de lição. Materiais com arquivo só são removidos pelo app. Confirme com a pessoa antes de remover.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'lesson_id' => $schema->integer()->description('Id do rascunho.')->required(),
            'type' => $schema->string()->enum(['block', 'reading', 'material'])->description('O que remover.')->required(),
            'id' => $schema->integer()->description('Id do item (veja get_lesson).')->required(),
        ];
    }

    public function handle(Request $request, SyncLessonSearchText $syncSearch, DeleteLessonMaterial $deleteMaterial): Response
    {
        $user = $this->actor($request);
        $data = $request->validate([
            'lesson_id' => ['required', 'integer'],
            'type' => ['required', 'in:block,reading,material'],
            'id' => ['required', 'integer'],
        ]);

        $lesson = $this->resolveLesson($user, $data['lesson_id']);
        $this->authorize($request, 'update', $lesson);
        $this->ensureDraft($lesson);

        $relation = match ($data['type']) {
            'block' => $lesson->blocks(),
            'reading' => $lesson->readings(),
            default => $lesson->materials(),
        };

        $item = $relation->whereKey($data['id'])->first()
            ?? throw ValidationException::withMessages(['id' => 'Item não encontrado nesta lição. Veja os ids com get_lesson.']);

        if ($item instanceof LessonMaterial && $item->hasFile()) {
            throw ValidationException::withMessages(['id' => 'Este material tem arquivo e só pode ser removido pelo app: '.route('admin.lessons.edit', $lesson)]);
        }

        $this->audited($request, $item, $lesson->classroom_id, function () use ($item, $lesson, $syncSearch, $deleteMaterial) {
            $item instanceof LessonMaterial ? $deleteMaterial->handle($item) : $item->delete();
            $syncSearch->handle($lesson);
        });

        return $this->json(['removed' => $data['type'], 'id' => $data['id']]);
    }
}
