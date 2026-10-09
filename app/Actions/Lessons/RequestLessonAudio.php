<?php

namespace App\Actions\Lessons;

use App\Models\Lesson;
use App\Support\Audio\OpenAiSpeech;
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

    public function handle(Lesson $lesson): string
    {
        if ($lesson->hasAudio() && ! $lesson->isAudioStale()) {
            return self::UP_TO_DATE;
        }

        if (blank($lesson->content)) {
            throw ValidationException::withMessages(['audio' => 'Escreva o estudo antes de gerar o áudio.']);
        }

        if (! $this->speech->isConfigured()) {
            throw ValidationException::withMessages(['audio' => 'A geração de áudio não está configurada no servidor (falta a chave da OpenAI).']);
        }

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
}
