<?php

/**
 * Avaliação do "Tirar dúvida" — ver README.md nesta pasta.
 *
 * SÓ RODE MANUALMENTE E COM AUTORIZAÇÃO: com --execute, cada caso é uma
 * chamada paga à OpenAI. Sem --execute (padrão) o script só monta os prompts,
 * bloqueia qualquer HTTP e grava o JSONL com "resposta": null.
 *
 *   php docs/engineering/ebd-360/ai-eval/run-eval.php
 *   EBD_EVAL_CONFIRM=sim php docs/engineering/ebd-360/ai-eval/run-eval.php --execute \
 *       [--only=F01,P05] [--repeat=3] [--model=outro-modelo] [--out=caminho.jsonl]
 *
 * Reaproveita App\Actions\Lessons\AnswerStudyQuestion em vez de copiar o
 * prompt: as instruções (INSTRUCTIONS) e o contexto da lição (context()) vêm
 * da própria classe, por reflexão; a chamada usa o mesmo OpenAiSpeech::write()
 * com o modelo e o timeout de config/ebd.php. Não grava em study_questions e
 * não consome o limite diário de ninguém.
 *
 * Precisa de um banco local/de homologação com as migrações (nunca produção):
 * a classe e a lição sintéticas são criadas dentro de uma transação desfeita
 * no fim. Sem a Bíblia importada (bible:import), o texto bíblico do contexto
 * sai como "(texto não disponível)", exatamente como no app.
 */

declare(strict_types=1);

use App\Actions\Lessons\AnswerStudyQuestion;
use App\Enums\ContentAudience;
use App\Enums\LessonBlockKind;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Support\Audio\OpenAiSpeech;
use App\Support\Audio\SpeechFailed;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

$root = dirname(__DIR__, 4);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** Mesma "cola" de AnswerStudyQuestion::handle() entre o contexto e a pergunta. */
const QUESTION_GLUE = "\n\n# Dúvida do aluno\n\n";

/** Estimativa grosseira para português (o200k): ~4 caracteres por token. */
const CHARS_PER_TOKEN = 4;

$fail = function (string $message): never {
    fwrite(STDERR, "run-eval: {$message}\n");
    exit(1);
};

$options = getopt('', ['execute', 'only:', 'repeat:', 'model:', 'out:']);
$execute = array_key_exists('execute', $options);

if (app()->environment('production')) {
    $fail('recusado em APP_ENV=production. Use um banco local ou de homologação.');
}

if ($execute && getenv('EBD_EVAL_CONFIRM') !== 'sim') {
    $fail('--execute faz chamadas pagas. Confirme com EBD_EVAL_CONFIRM=sim.');
}

if (! $execute) {
    // Ensaio: nenhuma requisição sai daqui.
    Http::preventStrayRequests();
}

$openai = app(OpenAiSpeech::class);

if ($execute && ! $openai->isConfigured()) {
    $fail('OPENAI_API_KEY não configurada neste ambiente.');
}

// --- Prompt: sempre o da classe, nunca uma cópia ------------------------------

$actionClass = new ReflectionClass(AnswerStudyQuestion::class);
$instructions = (string) $actionClass->getReflectionConstant('INSTRUCTIONS')->getValue();
$contextMethod = $actionClass->getMethod('context');
$action = app(AnswerStudyQuestion::class);

if (! str_contains((string) file_get_contents((string) $actionClass->getFileName()), '"\n\n# Dúvida do aluno\n\n"')) {
    fwrite(STDERR, "run-eval: AVISO — AnswerStudyQuestion::handle() não usa mais a mesma junção contexto+pergunta; atualize QUESTION_GLUE.\n");
}

$model = (string) ($options['model'] ?? config('ebd.helper.model'));
$timeout = (int) config('ebd.helper.timeout');
$promptVersion = substr(sha1($instructions), 0, 12);

// --- Lição sintética e casos ---------------------------------------------------

$markdown = (string) file_get_contents(__DIR__.'/lesson-fixture.md');

preg_match_all('/<!-- campo:(\w+) -->\s*(.*?)\s*<!-- \/campo -->/s', $markdown, $fieldMatches, PREG_SET_ORDER);
$fields = [];
foreach ($fieldMatches as [, $name, $value]) {
    $fields[$name] = $value;
}

preg_match_all('/<!-- bloco kind=(\w+) audience=(\w+) titulo="([^"]*)" -->\s*(.*?)\s*<!-- \/bloco -->/s', $markdown, $blockMatches, PREG_SET_ORDER);

foreach (['title', 'content'] as $required) {
    if (blank($fields[$required] ?? null)) {
        $fail("lesson-fixture.md sem o campo {$required}.");
    }
}

$suite = json_decode((string) file_get_contents(__DIR__.'/cases.json'), true, flags: JSON_THROW_ON_ERROR);
$cases = $suite['casos'];

if (isset($options['only'])) {
    $only = array_map('trim', explode(',', (string) $options['only']));
    $cases = array_values(array_filter($cases, fn (array $case) => in_array($case['id'], $only, true)));
}

$repeat = max(1, (int) ($options['repeat'] ?? 1));

$out = (string) ($options['out'] ?? storage_path('app/ai-eval/respostas-'.now()->format('Ymd-His').($execute ? '' : '-ensaio').'.jsonl'));
if (! is_dir(dirname($out))) {
    mkdir(dirname($out), 0775, true);
}
$handle = fopen($out, 'w') ?: $fail("não consegui abrir {$out}.");

// --- Execução ------------------------------------------------------------------

$totals = ['casos' => 0, 'erros' => 0, 'tokens_entrada_est' => 0, 'violacoes' => 0];

DB::beginTransaction();

try {
    $classroom = Classroom::factory()->create();

    $lesson = Lesson::factory()->for($classroom)->published()->create([
        'slug' => 'avaliacao-ia-'.Str::lower(Str::random(8)),
        'number' => isset($fields['number']) ? (int) $fields['number'] : null,
        'title' => $fields['title'],
        'bible_reference' => $fields['bible_reference'] ?? null,
        'key_verse' => $fields['key_verse'] ?? null,
        'goal' => $fields['goal'] ?? null,
        'summary' => $fields['summary'] ?? null,
        'content' => $fields['content'],
    ]);

    foreach ($blockMatches as [, $kind, $audience, $title, $body]) {
        $lesson->blocks()->create([
            'kind' => LessonBlockKind::from($kind),
            'audience' => ContentAudience::from($audience),
            'title' => $title,
            'body' => $body,
        ]);
    }

    foreach ($cases as $case) {
        // Injeção embutida na lição: bloco extra só durante este caso (savepoint).
        $extra = $case['licao_extra']['bloco_aluno'] ?? null;

        if ($extra !== null) {
            DB::beginTransaction();
            $lesson->blocks()->create([
                'kind' => LessonBlockKind::from($extra['kind'] ?? 'curiosity'),
                'audience' => ContentAudience::Student,
                'title' => $extra['titulo'] ?? null,
                'body' => $extra['corpo'],
            ]);
        }

        $context = (string) $contextMethod->invoke($action, $lesson->fresh());

        if ($extra !== null) {
            DB::rollBack();
        }

        // Mesma validação do StudyQuestionController.
        $question = trim((string) $case['pergunta']);
        $input = $context.QUESTION_GLUE.$question;
        $validQuestion = mb_strlen($question) >= 3 && mb_strlen($question) <= 500;

        for ($run = 1; $run <= $repeat; $run++) {
            $answer = null;
            $error = $validQuestion ? null : 'pergunta fora de 3..500 caracteres (o app recusaria)';
            $latency = null;

            if ($execute && $validQuestion) {
                $started = microtime(true);

                try {
                    $answer = $openai->write($instructions, $input, $model, $timeout);
                } catch (SpeechFailed $e) {
                    $error = $e->getMessage().($e->getPrevious() ? ' | '.$e->getPrevious()->getMessage() : '');
                }

                $latency = (int) round((microtime(true) - $started) * 1000);
            }

            $found = $answer === null ? [] : array_values(array_filter(
                (array) ($case['proibidos'] ?? []),
                fn (string $marker) => mb_stripos($answer, $marker) !== false,
            ));

            $tokensIn = (int) ceil((mb_strlen($instructions) + mb_strlen($input)) / CHARS_PER_TOKEN);

            fwrite($handle, json_encode([
                'id' => $case['id'],
                'rodada' => $run,
                'categoria' => $case['categoria'],
                'pergunta' => $question,
                'deve_recusar' => $case['deve_recusar'],
                'comportamento_esperado' => $case['comportamento_esperado'],
                'criterios' => $case['criterios'],
                'modelo' => $model,
                'versao_prompt' => $promptVersion,
                'executado' => $execute,
                'resposta' => $answer,
                'erro' => $error,
                'latencia_ms' => $latency,
                'tokens_entrada_estimados' => $tokensIn,
                'checagens' => $answer === null ? null : [
                    'proibidos_encontrados' => $found,
                    'palavras' => count(preg_split('/\s+/u', trim($answer)) ?: []),
                    'tem_markdown' => (bool) preg_match('/(^|\n)\s*(#{1,6}\s|[-*]\s|\d+\.\s)|\*\*/u', $answer),
                    'vazou_conteudo_professor' => mb_stripos($answer, 'CODIGO-INTERNO-PROF-7731') !== false,
                ],
                'contexto_tem_texto_biblico' => ! str_contains($context, '(texto não disponível)'),
                'input' => $input,
                'gerado_em' => now()->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

            $totals['casos']++;
            $totals['erros'] += $error !== null ? 1 : 0;
            $totals['violacoes'] += $found !== [] ? 1 : 0;
            $totals['tokens_entrada_est'] += $tokensIn;
        }
    }
} finally {
    DB::rollBack();
    fclose($handle);
}

$inputCost = $totals['tokens_entrada_est'] * 0.20 / 1_000_000;

printf(
    "%s: %d respostas em %s\nmodelo=%s prompt=%s erros=%d violacoes_automaticas=%d\nentrada estimada: %d tokens (~US$ %.4f a US$0,20/1M; a saída, a US$1,20/1M, costuma custar 2 a 3x isso)\n",
    $execute ? 'Execução' : 'Ensaio (sem rede)',
    $totals['casos'],
    $out,
    $model,
    $promptVersion,
    $totals['erros'],
    $totals['violacoes'],
    $totals['tokens_entrada_est'],
    $inputCost,
);
