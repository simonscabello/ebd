<?php

namespace App\Support\Push;

/**
 * O que aparece na notificação. `url` é aberta ao tocar; `tag` faz uma
 * notificação nova substituir a anterior de mesmo assunto (ex.: o lembrete
 * da noite substitui o da manhã, se a pessoa ainda não viu).
 */
final readonly class PushMessage
{
    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public ?string $tag = null,
    ) {}

    /**
     * Recorte curto para o corpo da notificação: a primeira frase, se couber;
     * senão, corta no fim de uma palavra e põe reticências. Nunca no meio de
     * uma palavra (o celular já corta em ~3 linhas).
     */
    public static function excerpt(?string $text, int $limit = 120): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        if (preg_match('/^(.{1,'.$limit.'}?[.!?])(?:\s|$)/su', $text, $match)) {
            return $match[1];
        }

        $cut = mb_substr($text, 0, $limit);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > 0 ? mb_substr($cut, 0, $space) : $cut, ' ,;:').'…';
    }

    /**
     * @return array{title: string, body: string, url: string, tag: string|null}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tag' => $this->tag,
        ];
    }
}
