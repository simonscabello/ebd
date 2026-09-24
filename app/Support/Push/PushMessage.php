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
