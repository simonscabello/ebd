<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Conteúdo das lições é escrito em Markdown e convertido no servidor.
 * HTML bruto é removido e links inseguros (javascript:, data:) são bloqueados,
 * então o resultado pode ser exibido com segurança no front-end.
 */
class Markdown
{
    public static function toHtml(?string $markdown): ?string
    {
        if (blank($markdown)) {
            return null;
        }

        return trim(Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]));
    }

    /**
     * Títulos de seção (## e ###) usados como tópicos no Modo Domingo.
     *
     * @return list<string>
     */
    public static function headings(?string $markdown): array
    {
        if (blank($markdown)) {
            return [];
        }

        preg_match_all('/^#{2,3}\s+(.+?)\s*#*\s*$/m', $markdown, $matches);

        return array_map(
            fn (string $heading) => trim(strip_tags(Str::inlineMarkdown($heading, ['html_input' => 'strip']))),
            $matches[1],
        );
    }
}
