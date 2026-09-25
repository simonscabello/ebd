<?php

namespace App\Console\Commands;

use App\Actions\Lessons\CreateLesson;
use App\Actions\Lessons\SyncLessonSearchText;
use App\Actions\Lessons\UpdateLesson;
use App\Enums\ContentAudience;
use App\Enums\LessonBlockKind;
use App\Enums\LessonStatus;
use App\Enums\LessonVisibility;
use App\Enums\MaterialType;
use App\Enums\Weekday;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\Series;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Cadastra uma lição inteira (dados, leituras, blocos e materiais com link) a
 * partir de um JSON, sempre como rascunho: a revisão e a publicação continuam
 * no app. Evita copiar campo por campo o conteúdo preparado fora dele.
 *
 * Formato: os campos do formulário da lição no primeiro nível (number, title,
 * summary, bible_reference, key_verse, goal, content, visibility), mais
 * `classroom` (slug), `series` (slug, opcional), `author` (e-mail de quem cria),
 * `meeting_on` (domingo da aula, opcional) e as listas `readings`, `blocks` e
 * `materials`. Materiais com arquivo continuam sendo enviados pelo app.
 *
 * Com o caminho "-", lê o JSON da entrada padrão, para rodar em produção num
 * comando só: `railway ssh -- php artisan lesson:import - --force < licao.json`.
 */
#[Signature('lesson:import
    {path : Caminho do JSON da lição ("-" para ler da entrada padrão)}
    {--replace : Substitui o conteúdo do rascunho que já tem este número na série}
    {--dry-run : Só confere o arquivo, a classe, a série e o autor, sem gravar}
    {--force : Não pede confirmação (uso em scripts)}')]
#[Description('Cadastra uma lição (rascunho) a partir de um JSON')]
class ImportLesson extends Command
{
    private const array LESSON_FIELDS = ['number', 'title', 'summary', 'bible_reference', 'key_verse', 'goal', 'content', 'visibility'];

    /** Materiais que não dependem de arquivo enviado. */
    private const array LINK_MATERIALS = [MaterialType::Reference, MaterialType::Link, MaterialType::Video, MaterialType::Audio];

    public function handle(CreateLesson $create, UpdateLesson $update, SyncLessonSearchText $syncSearch): int
    {
        $path = (string) $this->argument('path');

        if ($path === '-' && ! $this->option('force') && ! $this->option('dry-run')) {
            // A entrada padrão já traz o JSON; não sobra de onde ler a confirmação.
            $this->components->error('Ao ler da entrada padrão, use --force.');

            return self::FAILURE;
        }

        if ($path !== '-' && (! is_file($path) || ! is_readable($path))) {
            $this->components->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($path === '-' ? 'php://stdin' : $path), true);

        if (! is_array($data)) {
            $this->components->error('O arquivo não é um JSON válido.');

            return self::FAILURE;
        }

        $validator = Validator::make($data, $this->rules());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $data = $validator->validated();
        $classroom = Classroom::query()->where('slug', $data['classroom'])->first();

        if ($classroom === null) {
            $this->components->error("A classe \"{$data['classroom']}\" não existe. Classes: ".Classroom::query()->orderBy('position')->pluck('slug')->implode(', ').'.');

            return self::FAILURE;
        }

        $series = filled($data['series'] ?? null)
            ? Series::query()->whereBelongsTo($classroom)->where('slug', $data['series'])->first()
            : null;

        if (filled($data['series'] ?? null) && $series === null) {
            $this->components->error("A série \"{$data['series']}\" não existe na classe {$classroom->name}. Séries: ".Series::query()->whereBelongsTo($classroom)->pluck('slug')->implode(', ').'.');

            return self::FAILURE;
        }

        $author = User::query()->where('email', $data['author'])->firstOrFail();
        $existing = isset($data['number'])
            ? Lesson::query()->whereBelongsTo($classroom)->where('series_id', $series?->id)->where('number', $data['number'])->first()
            : null;

        if ($existing !== null && ! $this->option('replace')) {
            $this->components->error("Já existe a {$existing->displayTitle()}. Use --replace para substituir o rascunho.");

            return self::FAILURE;
        }

        if ($existing !== null && $existing->status !== LessonStatus::Draft) {
            $this->components->error("A {$existing->displayTitle()} já foi publicada. Edite-a pelo app.");

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%s "%s" em %s%s: %d leituras, %d blocos, %d materiais. Destino: %s.',
            $existing ? 'Substituir' : 'Criar',
            $data['title'],
            $classroom->name,
            $series ? " / {$series->title}" : '',
            count($data['readings'] ?? []),
            count($data['blocks'] ?? []),
            count($data['materials'] ?? []),
            $this->target(),
        ));

        if ($existing !== null) {
            $this->components->warn('Leituras, blocos e materiais sem arquivo do rascunho atual serão apagados.');
        }

        if ($this->option('dry-run')) {
            $this->components->info('Conferência concluída: nada foi gravado.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Continuar?')) {
            $this->components->warn('Importação cancelada.');

            return self::FAILURE;
        }

        $lesson = DB::transaction(function () use ($data, $classroom, $series, $author, $existing, $create, $update) {
            $fields = Arr::only($data, self::LESSON_FIELDS) + ['series_id' => $series?->id];

            if ($existing !== null) {
                $lesson = $update->handle($existing, $fields);
                $lesson->readings()->delete();
                $lesson->blocks()->delete();
                $lesson->materials()->whereNull('path')->delete();
            } else {
                $lesson = $create->handle($author, $classroom, $fields + [
                    'author_ids' => [$author->id],
                    'meeting_on' => $data['meeting_on'] ?? null,
                ]);
            }

            foreach ($data['readings'] ?? [] as $reading) {
                $lesson->readings()->create($reading);
            }

            foreach ($data['blocks'] ?? [] as $block) {
                $block['audience'] ??= LessonBlockKind::from($block['kind'])->defaultAudience()->value;

                if ($block['audience'] === ContentAudience::Teacher->value) {
                    $block['drip_weekday'] = null;
                }

                $lesson->blocks()->create($block);
            }

            foreach ($data['materials'] ?? [] as $material) {
                $material['audience'] ??= ContentAudience::Student->value;
                $record = new LessonMaterial($material);
                $record->lesson_id = $lesson->id;
                $record->save();
            }

            return $lesson;
        });

        $syncSearch->handle($lesson);

        if ($existing !== null && filled($data['meeting_on'] ?? null)) {
            $this->components->warn('O domingo da aula não muda na substituição. Ajuste pela agenda da classe.');
        }

        $this->components->info("Pronto: {$lesson->displayTitle()} (rascunho). Revise e publique em ".route('admin.lessons.edit', $lesson));

        return self::SUCCESS;
    }

    /**
     * As mesmas regras dos formulários do app, sem as que dependem de quem edita.
     *
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'classroom' => ['required', 'string'],
            'series' => ['nullable', 'string'],
            'author' => ['required', 'email', 'exists:users,email'],
            'meeting_on' => ['nullable', 'date'],
            'number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'bible_reference' => ['nullable', 'string', 'max:120'],
            'key_verse' => ['nullable', 'string', 'max:160'],
            'goal' => ['nullable', 'string', 'max:2000'],
            'content' => ['nullable', 'string', 'max:100000'],
            'visibility' => ['required', Rule::enum(LessonVisibility::class)],
            'readings' => ['nullable', 'array', 'max:14'],
            'readings.*.weekday' => ['nullable', Rule::enum(Weekday::class)],
            'readings.*.reference' => ['required', 'string', 'max:160'],
            'readings.*.notes' => ['nullable', 'string', 'max:1000'],
            'blocks' => ['nullable', 'array', 'max:60'],
            'blocks.*.kind' => ['required', Rule::enum(LessonBlockKind::class)],
            'blocks.*.audience' => ['nullable', Rule::enum(ContentAudience::class)],
            'blocks.*.title' => ['nullable', 'string', 'max:180'],
            'blocks.*.body' => ['required', 'string', 'max:60000'],
            'blocks.*.drip_weekday' => ['nullable', Rule::enum(Weekday::class)],
            'materials' => ['nullable', 'array', 'max:30'],
            'materials.*.type' => ['required', Rule::in(array_map(fn (MaterialType $type) => $type->value, self::LINK_MATERIALS))],
            'materials.*.audience' => ['nullable', Rule::enum(ContentAudience::class)],
            'materials.*.title' => ['required', 'string', 'max:180'],
            'materials.*.description' => ['nullable', 'string', 'max:2000'],
            'materials.*.url' => ['nullable', 'required_unless:materials.*.type,reference', 'url:http,https', 'max:2048'],
        ];
    }

    private function target(): string
    {
        $connection = DB::connection();

        return sprintf('%s@%s/%s', (string) $connection->getConfig('username'), (string) $connection->getConfig('host'), $connection->getDatabaseName());
    }
}
