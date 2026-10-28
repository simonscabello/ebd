<?php

namespace App\Actions\Lessons;

use App\Enums\AiUsageKind;
use App\Models\AiUsage;
use App\Models\Lesson;
use App\Support\Audio\OpenAiSpeech;
use App\Support\ChurchFeatures;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Pede o áudio do estudo. A marcação "gerando" é feita com um UPDATE
 * condicional, então cliques repetidos (ou duas pessoas ao mesmo tempo)
 * disparam uma geração só.
 *
 * A geração roda depois que a resposta é enviada, no mesmo processo: o
 * servidor não tem worker de fila, e a página acompanha o andamento.
 */
class RequestLessonAudio
{
    public const STARTED = 'started';

    public const ALREADY_RUNNING = 'running';

    public const UP_TO_DATE = 'current';

    public function __construct(
        private OpenAiSpeech $speech,
        private GenerateLessonAudio $generate,
    ) {}

    /**
     * @param  bool  $force  gera de novo mesmo com o áudio em dia (só administrador, ver LessonAudioController)
     */
    public function handle(Lesson $lesson, bool $force = false): string
    {
        if (! $force && $lesson->hasAudio() && ! $lesson->isAudioStale()) {
            return self::UP_TO_DATE;
        }

        if (! ChurchFeatures::audioAvailable($lesson)) {
            throw new AuthorizationException('O áudio do estudo não está liberado para esta igreja.');
        }

        if (blank($lesson->content)) {
            throw ValidationException::withMessages(['audio' => 'Escreva o estudo antes de gerar o áudio.']);
        }

        if (! $this->speech->isConfigured()) {
            throw ValidationException::withMessages(['audio' => 'A geração de áudio não está configurada no servidor (falta a chave da OpenAI).']);
        }

        // Contar e reservar sob um lock da igreja: dois pedidos ao mesmo tempo, em
        // lições diferentes, não passam juntos pela última vaga do mês.
        $church = ChurchFeatures::churchOf($lesson);
        $lock = Cache::lock("church-audio:{$church->id}", 10);

        if (! $lock->get()) {
            throw ValidationException::withMessages(['audio' => 'Outro áudio desta igreja está sendo pedido agora. Tente de novo em instantes.']);
        }

        try {
            $claimed = $this->claim($lesson);
        } finally {
            $lock->release();
        }

        if ($claimed === 0) {
            return self::ALREADY_RUNNING;
        }

        // Uma vez só: em processos longos (testes, workers) os callbacks de
        // término ficam registrados e rodam de novo a cada requisição.
        $pending = true;

        app()->terminating(function () use (&$pending, $lesson) {
            if ($pending) {
                $pending = false;
                $this->generate->handle($lesson);
            }
        });

        return self::STARTED;
    }

    /**
     * Marca a lição como "gerando" se ainda cabe no limite do mês e já conta
     * a tentativa na cota da igreja (mesmo que a geração falhe depois, o
     * gasto com a OpenAI já aconteceu). Devolve 0 quando outra geração da
     * mesma lição já está em andamento. Roda sob o lock da igreja.
     *
     * @throws ValidationException
     */
    private function claim(Lesson $lesson): int
    {
        // Clique repetido durante a geração: não gasta outra vaga nem esbarra no limite.
        if ($lesson->fresh()?->isGeneratingAudio()) {
            return 0;
        }

        $church = ChurchFeatures::churchOf($lesson);

        if (ChurchFeatures::audioRemainingThisMonth($lesson) === 0) {
            throw ValidationException::withMessages(['audio' => "Limite de áudios do mês atingido ({$church->audio_monthly_limit} por mês)."]);
        }

        // Uma geração que passou do prazo pode ser pedida de novo (o processo
        // pode ter morrido). Se a antiga ainda estiver viva, ela não grava por
        // cima: GenerateLessonAudio só salva se o pedido ainda for o dela.
        $claimed = Lesson::query()
            ->whereKey($lesson->id)
            ->where(fn ($query) => $query
                ->whereNull('audio_status')
                ->orWhere('audio_status', '!=', Lesson::AUDIO_GENERATING)
                ->orWhere('audio_requested_at', '<', now()->subMinutes(Lesson::AUDIO_GENERATION_TIMEOUT_MINUTES)))
            ->update([
                'audio_status' => Lesson::AUDIO_GENERATING,
                'audio_error' => null,
                'audio_requested_at' => now(),
            ]);

        if ($claimed > 0) {
            AiUsage::record($church, AiUsageKind::Audio, $lesson);
        }

        return $claimed;
    }
}
