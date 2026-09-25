<?php

namespace App\Mcp\Tools;

use App\Concerns\LessonValidationRules;
use App\Mcp\Concerns\DescribesLessonParts;
use App\Mcp\Presenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class SaveLessonReading extends EbdTool
{
    use DescribesLessonParts;
    use LessonValidationRules;

    protected string $name = 'save_lesson_reading';

    protected string $title = 'Salvar leitura da semana';

    protected string $description = 'Adiciona uma leitura bíblica da semana a um rascunho de lição, ou edita uma existente (reading_id; campos omitidos ficam como estão). Os alunos marcam essas leituras em "Minha semana".';

    public function schema(JsonSchema $schema): array
    {
        return [
            'lesson_id' => $schema->integer()->description('Id do rascunho.')->required(),
            'reading_id' => $schema->integer()->description('Id da leitura a editar (veja get_lesson). Omita para adicionar.'),
            ...$this->readingProperties($schema),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->actor($request);
        $lesson = $this->resolveLesson($user, $request->get('lesson_id'));
        $this->authorize($request, 'update', $lesson);
        $this->ensureDraft($lesson);

        $reading = null;

        if ($request->get('reading_id') !== null) {
            $reading = $lesson->readings()->whereKey((int) $request->get('reading_id'))->first()
                ?? throw ValidationException::withMessages(['reading_id' => 'Leitura não encontrada nesta lição. Veja os ids com get_lesson.']);

            $this->keepCurrentValues($request, Arr::only(Presenter::reading($reading), ['weekday', 'reference', 'notes']));
        }

        $rules = $this->lessonReadingRules();
        $data = array_intersect_key($request->validate([...$rules, 'lesson_id' => ['required', 'integer'], 'reading_id' => ['nullable', 'integer']], [], [...$this->lessonPartAttributes(), 'title' => 'título']), $rules);

        $reading = $this->auditedSave($request, $reading, $lesson->classroom_id, function () use ($lesson, $reading, $data) {
            if ($reading === null) {
                return $lesson->readings()->create($data);
            }

            $reading->update($data);

            return $reading;
        });

        return $this->json([
            'saved' => $request->get('reading_id') !== null ? 'updated' : 'created',
            'reading' => Presenter::reading($reading->refresh()),
        ]);
    }
}
