<?php

namespace App\Mcp\Tools;

use App\Actions\Lessons\ImportLessonDraft;
use App\Concerns\LessonValidationRules;
use App\Enums\LessonVisibility;
use App\Mcp\Concerns\DescribesLessonParts;
use App\Mcp\Presenter;
use App\Models\Lesson;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class SaveLessonDraft extends EbdTool
{
    use DescribesLessonParts;
    use LessonValidationRules;

    protected string $name = 'save_lesson_draft';

    protected string $title = 'Salvar lição (rascunho)';

    protected string $description = <<<'TEXT'
        Cria uma lição como rascunho ou edita um rascunho existente (lesson_id). Lições publicadas não podem ser alteradas por aqui.
        Na criação informe classroom e title; series e meeting_on (domingo da aula) são opcionais.
        Na edição, só os campos enviados mudam. As listas readings, blocks e materials, quando enviadas, SUBSTITUEM as atuais por inteiro;
        para mexer em um item só, use save_lesson_block, save_lesson_reading ou save_lesson_material.
        A lição fica como rascunho: a publicação é feita pela pessoa no app (link edit_url).
        TEXT;

    public function schema(JsonSchema $schema): array
    {
        return [
            'lesson_id' => $schema->integer()->description('Id do rascunho a editar. Omita para criar uma lição nova.'),
            'classroom' => $schema->string()->description('Slug, nome ou id da classe (obrigatório na criação).'),
            'series' => $schema->string()->description('Slug ou id da série da revista.'),
            'meeting_on' => $schema->string()->format('date')->description('Domingo da aula (AAAA-MM-DD), só na criação. Ajustes depois pela agenda (save_meeting).'),
            'number' => $schema->integer()->description('Número da lição na revista (único na série).'),
            'title' => $schema->string()->description('Título da lição.'),
            'summary' => $schema->string()->description('Resumo curto.'),
            'bible_reference' => $schema->string()->description('Texto bíblico da lição, ex.: "Gênesis 12.1-9".'),
            'key_verse' => $schema->string()->description('Versículo-chave (referência), ex.: "Hebreus 11.8".'),
            'goal' => $schema->string()->description('Alvo da lição.'),
            'content' => $schema->string()->description('Conteúdo da lição em Markdown (use ## para os tópicos).'),
            'visibility' => $schema->string()->enum(LessonVisibility::class)->description('public (qualquer pessoa com o link, padrão) ou members (só membros da classe), valendo depois de publicada.'),
            'readings' => $schema->array()->items($schema->object($this->readingProperties($schema)))->description('Leituras da semana. Substitui todas as atuais.'),
            'blocks' => $schema->array()->items($schema->object($this->blockProperties($schema)))->description('Blocos (roteiro, contexto, curiosidades...). Substitui todos os atuais.'),
            'materials' => $schema->array()->items($schema->object($this->materialProperties($schema)))->description('Materiais com link. Substitui os atuais (materiais com arquivo continuam).'),
        ];
    }

    public function handle(Request $request, ImportLessonDraft $import): Response
    {
        $user = $this->actor($request);
        $existing = $request->get('lesson_id') !== null ? $this->resolveLesson($user, $request->get('lesson_id')) : null;

        if ($existing !== null) {
            $this->authorize($request, 'update', $existing);
            $this->ensureDraft($existing);
            $classroom = $existing->classroom;
        } else {
            if (blank($request->get('classroom'))) {
                throw ValidationException::withMessages(['classroom' => 'Informe a classe (classroom) para criar a lição, ou lesson_id para editar um rascunho.']);
            }

            $classroom = $this->resolveClassroom($user, $request->get('classroom'));
            $this->authorize($request, 'manageContent', $classroom);
        }

        $data = $request->validate($this->rules($existing), [], [
            ...$this->lessonAttributes(),
            ...$this->lessonPartAttributes('readings.*.'),
            ...$this->lessonPartAttributes('blocks.*.'),
            ...$this->lessonPartAttributes('materials.*.'),
        ]);

        if (array_key_exists('series', $data)) {
            $data['series_id'] = filled($data['series']) ? $this->resolveSeries($classroom, $data['series'])->id : null;
        }

        $this->ensureUniqueNumber($data, $existing);

        if ($existing === null) {
            $data['visibility'] ??= LessonVisibility::Public->value;
        } elseif (array_key_exists('visibility', $data) && $data['visibility'] === null) {
            unset($data['visibility']);
        }

        $lesson = $this->auditedSave(
            $request,
            $existing,
            $classroom->id,
            fn () => $import->handle($user, $classroom, Arr::except($data, ['lesson_id', 'classroom', 'series']), $existing),
            fn (Lesson $lesson) => $this->snapshot($lesson),
        );

        $lesson->load(['classroom', 'series', 'authors', 'readings', 'blocks', 'materials', 'meetings']);

        return $this->json([
            'saved' => $existing === null ? 'created' : 'updated',
            'message' => 'Rascunho salvo. Para publicar, a pessoa revisa e publica no app: '.route('admin.lessons.edit', $lesson),
            'lesson' => Presenter::lesson($lesson),
        ]);
    }

    /**
     * Na edição, cada campo só é validado se vier (e, vindo, segue a regra do formulário).
     *
     * @return array<string, mixed>
     */
    private function rules(?Lesson $existing): array
    {
        $fields = $this->lessonFieldRules();
        $fields['visibility'] = ['nullable', Rule::enum(LessonVisibility::class)];

        if ($existing !== null) {
            $fields = array_map(fn (array $rules) => ['sometimes', ...$rules], $fields);
        }

        return [
            'lesson_id' => ['nullable', 'integer'],
            'classroom' => ['nullable'],
            'series' => ['nullable'],
            'meeting_on' => $existing ? ['prohibited'] : ['nullable', 'date_format:Y-m-d'],
            ...$fields,
            'readings' => ['sometimes', 'array', 'max:14'],
            ...$this->lessonReadingRules('readings.*.'),
            'blocks' => ['sometimes', 'array', 'max:60'],
            ...$this->lessonBlockRules('blocks.*.'),
            'materials' => ['sometimes', 'array', 'max:30'],
            ...$this->lessonLinkMaterialRules('materials.*.'),
        ];
    }

    /**
     * O número da revista é único dentro da série (índice parcial no banco).
     *
     * @param  array<string, mixed>  $data
     */
    private function ensureUniqueNumber(array $data, ?Lesson $existing): void
    {
        $number = $data['number'] ?? $existing?->number;
        $seriesId = array_key_exists('series_id', $data) ? $data['series_id'] : $existing?->series_id;

        if ($number === null || $seriesId === null) {
            return;
        }

        $taken = Lesson::query()
            ->where('series_id', $seriesId)
            ->where('number', $number)
            ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))
            ->first();

        if ($taken !== null) {
            throw ValidationException::withMessages([
                'number' => "Já existe a {$taken->displayTitle()} (id {$taken->id}) nesta série. Edite essa lição com lesson_id ou use outro número.",
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Lesson $lesson): array
    {
        $lesson->loadMissing(['readings', 'blocks', 'materials']);

        return [
            ...Arr::only($lesson->attributesToArray(), [...ImportLessonDraft::LESSON_FIELDS, 'series_id', 'slug']),
            'readings' => $lesson->readings->map(fn ($r) => Arr::only($r->attributesToArray(), ['weekday', 'reference', 'notes']))->all(),
            'blocks' => $lesson->blocks->map(fn ($b) => Arr::only($b->attributesToArray(), ['kind', 'audience', 'title', 'body', 'drip_weekday']))->all(),
            'materials' => $lesson->materials->map(fn ($m) => Arr::only($m->attributesToArray(), ['type', 'audience', 'title', 'description', 'url']))->all(),
        ];
    }
}
