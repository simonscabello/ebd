<?php

namespace App\Support\Audio;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Narra um trecho de texto pela API de voz da OpenAI e devolve o MP3; também
 * pede ao modelo de texto o roteiro para ouvir.
 * A chave (OPENAI_API_KEY) só existe no servidor.
 */
class OpenAiSpeech
{
    private const ENDPOINT = 'https://api.openai.com/v1/audio/speech';

    private const RESPONSES_ENDPOINT = 'https://api.openai.com/v1/responses';

    public function isConfigured(): bool
    {
        return filled(config('services.openai.key'));
    }

    /**
     * @throws SpeechFailed com mensagem amigável
     */
    public function synthesize(string $text, string $voice): string
    {
        $response = $this->post(self::ENDPOINT, array_filter([
            'model' => config('ebd.audio.model'),
            'voice' => $voice,
            'input' => $text,
            'instructions' => config('ebd.audio.instructions'),
            'response_format' => 'mp3',
        ]));

        $audio = $response->body();

        if ($audio === '' || ! str_contains((string) $response->header('Content-Type'), 'audio')) {
            throw SpeechFailed::because('A OpenAI devolveu uma resposta inesperada. Tente de novo em alguns minutos.');
        }

        return $audio;
    }

    /**
     * Texto gerado por um modelo de texto (roteiro para ouvir, ver ListeningScript).
     *
     * @throws SpeechFailed com mensagem amigável
     */
    public function write(string $instructions, string $input): string
    {
        $response = $this->post(self::RESPONSES_ENDPOINT, [
            'model' => config('ebd.audio.script_model'),
            'instructions' => $instructions,
            'input' => $input,
            'reasoning' => ['effort' => 'low'],
        ]);

        $text = collect((array) $response->json('output'))
            ->where('type', 'message')
            ->flatMap(fn (array $message) => (array) ($message['content'] ?? []))
            ->where('type', 'output_text')
            ->pluck('text')
            ->implode('');

        if (trim($text) === '') {
            throw SpeechFailed::because('A OpenAI devolveu uma resposta inesperada. Tente de novo em alguns minutos.');
        }

        return trim($text);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws SpeechFailed
     */
    private function post(string $endpoint, array $payload): Response
    {
        if (! $this->isConfigured()) {
            throw SpeechFailed::because('A geração de áudio não está configurada no servidor (falta a chave da OpenAI).');
        }

        try {
            return Http::withToken((string) config('services.openai.key'))
                ->timeout((int) config('ebd.audio.timeout'))
                ->retry(3, 2000, fn ($exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && in_array($exception->response->status(), [429, 500, 502, 503, 504], true)
                        && ! $this->isQuotaError($exception->response)))
                ->post($endpoint, $payload)
                ->throw();
        } catch (ConnectionException $e) {
            throw SpeechFailed::because('Não foi possível falar com a OpenAI agora. Tente de novo em alguns minutos.', $e);
        } catch (RequestException $e) {
            throw SpeechFailed::because($this->friendlyMessage($e->response), $e);
        }
    }

    private function friendlyMessage(Response $response): string
    {
        return match (true) {
            $response->status() === 401 => 'A OpenAI recusou a chave configurada no servidor. Confira a OPENAI_API_KEY.',
            $this->isQuotaError($response) => 'A conta da OpenAI está sem créditos ou passou do limite de uso.',
            $response->status() === 429 => 'A OpenAI está recebendo pedidos demais agora. Tente de novo em alguns minutos.',
            $response->serverError() => 'A OpenAI está instável no momento. Tente de novo em alguns minutos.',
            default => 'A OpenAI não aceitou o pedido de áudio. O erro ficou registrado.',
        };
    }

    private function isQuotaError(Response $response): bool
    {
        return $response->status() === 429 && $response->json('error.code') === 'insufficient_quota';
    }
}
