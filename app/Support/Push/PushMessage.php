<?php

namespace App\Support\Push;

/**
 * O que aparece na notificação. `url` é aberta ao tocar; `tag` faz uma
 * notificação nova substituir a anterior de mesmo assunto (ex.: o lembrete
 * da noite substitui o da manhã, se a pessoa ainda não viu).
 *
 * O Android mostra o título numa linha só, mesmo com a notificação aberta:
 * ele é limitado aqui para nunca ser cortado ao acaso. O corpo pode ter
 * quebras de linha e ganha umas 5 linhas quando a notificação é aberta.
 */
final readonly class PushMessage
{
    public const TITLE_LIMIT = 40;

    public string $title;

    public function __construct(
        string $title,
        public string $body,
        public string $url,
        public ?string $tag = null,
    ) {
        $this->title = self::clip(trim($title), self::TITLE_LIMIT);
    }

    /**
     * Recorte para o corpo da notificação: as frases inteiras que couberem;
     * senão, o fim de um trecho da frase (travessão, ponto e vírgula, dois
     * pontos ou vírgula); senão, o fim de uma palavra. Nunca no meio de uma
     * palavra.
     */
    public static function excerpt(?string $text, int $limit = 200): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        if (preg_match('/^(.{1,'.($limit - 1).'}[.!?])(?:\s|$)/su', $text, $match)) {
            return $match[1];
        }

        $cut = mb_substr($text, 0, $limit - 1);

        if (preg_match('/^(.{'.intdiv($limit, 2).',}?)\s*[—;:,][^—;:,]*$/su', $cut, $match)) {
            return rtrim($match[1]).'…';
        }

        return self::clip($text, $limit);
    }

    /** Corta no fim de uma palavra e põe reticências, sem passar de `$limit`. */
    private static function clip(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > 0 ? mb_substr($cut, 0, $space) : $cut, ' ,;:—-').'…';
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
