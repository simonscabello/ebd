<?php

namespace App\Console\Commands;

use App\Actions\Lessons\ImportLessonDraft;
use App\Concerns\LessonValidationRules;
use App\Enums\LessonStatus;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
    use LessonValidationRules;

    public function handle(ImportLessonDraft $import): int
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

        // Na substituição, as três listas são trocadas mesmo quando vêm vazias no arquivo.
        $lesson = $import->handle($author, $classroom, Arr::only($data, [...ImportLessonDraft::LESSON_FIELDS, 'meeting_on']) + [
            'series_id' => $series?->id,
            'readings' => $data['readings'] ?? [],
            'blocks' => $data['blocks'] ?? [],
            'materials' => $data['materials'] ?? [],
        ], $existing);

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
            ...$this->lessonFieldRules(),
            'readings' => ['nullable', 'array', 'max:14'],
            ...$this->lessonReadingRules('readings.*.'),
            'blocks' => ['nullable', 'array', 'max:60'],
            ...$this->lessonBlockRules('blocks.*.'),
            'materials' => ['nullable', 'array', 'max:30'],
            ...$this->lessonLinkMaterialRules('materials.*.'),
        ];
    }

    private function target(): string
    {
        $connection = DB::connection();

        return sprintf('%s@%s/%s', (string) $connection->getConfig('username'), (string) $connection->getConfig('host'), $connection->getDatabaseName());
    }
}
