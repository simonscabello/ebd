<?php

namespace App\Support\Bible;

use App\Enums\BibleBook;

/**
 * Torna clicáveis as referências bíblicas escritas no meio do texto das lições
 * ("(Hb 2.4)", "(At 5.12-13; At 8.6-8; 9.35,42)").
 *
 * Cada referência vira um botão `.bible-ref`. Referências em sequência
 * (separadas por ";") formam um grupo: o atributo `data-bible` de cada botão
 * traz o grupo inteiro, em forma canônica e separado por "|", e o front abre
 * todos os trechos no mesmo painel.
 *
 * Para não confundir texto comum com referência ("Os 12 apóstolos"), capítulo
 * sem versículo só vale com o nome do livro por extenso ("Salmo 23", "Atos 2").
 * Trechos dentro de links e de código ficam como estão.
 */
final class BibleLinks
{
    private static ?string $pattern = null;

    private static ?string $bookPattern = null;

    public static function link(string $html): string
    {
        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $html;
        }

        $skip = 0;

        foreach ($parts as $index => $part) {
            if (str_starts_with($part, '<')) {
                if (preg_match('/^<(\/?)(a|code|pre|button)\b/i', $part, $tag)) {
                    $skip = max(0, $skip + ($tag[1] === '/' ? -1 : 1));
                }

                continue;
            }

            if ($skip === 0 && $part !== '') {
                $parts[$index] = (string) preg_replace_callback(
                    self::pattern(),
                    fn (array $match) => self::linkSequence($match[0]),
                    $part,
                );
            }
        }

        return implode('', $parts);
    }

    private static function linkSequence(string $sequence): string
    {
        $pieces = preg_split('/(\s*;\s*)/u', $sequence, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$sequence];
        $book = null;
        $bookText = '';
        $labels = [];

        // Índice da peça => forma canônica (null = fica como texto).
        $resolved = [];

        foreach ($pieces as $index => $piece) {
            if ($index % 2 === 1) {
                continue;
            }

            if (preg_match('/^(?<book>'.self::bookPattern().')\.?\s*(?<rest>.+)$/u', $piece, $match)) {
                $book = BibleBook::fromName($match['book']);
                $bookText = $match['book'];
                $rest = $match['rest'];
            } else {
                $rest = $piece;
            }

            $wholeChapters = ! preg_match('/\d\s*[.:]\s*\d/', $rest);
            $shortName = mb_strlen((string) preg_replace('/[^\p{L}]/u', '', $bookText)) < 4;

            $reference = $book === null || ($wholeChapters && $shortName)
                ? null
                : Reference::parse($book->label().' '.$rest);

            $resolved[$index] = $reference?->label();

            if ($reference !== null) {
                $labels[] = $reference->label();
            }
        }

        if ($labels === []) {
            return $sequence;
        }

        $group = e(implode('|', $labels));
        $html = '';

        foreach ($pieces as $index => $piece) {
            if ($index % 2 === 1) {
                continue;
            }

            $label = $resolved[$index] ?? null;
            $text = $label === null
                ? $piece
                : '<button type="button" class="bible-ref" data-bible="'.$group.'" aria-haspopup="dialog">'.$piece.'</button>';

            // O ";" seguinte fica junto da referência, para não começar uma
            // linha sozinho (o botão é inline-block e quebra antes dele).
            $separator = $pieces[$index + 1] ?? '';
            $punctuation = rtrim($separator);

            $html .= $punctuation === ''
                ? $text.$separator
                : '<span class="bible-ref-group">'.$text.$punctuation.'</span>'.substr($separator, strlen($punctuation));
        }

        return $html;
    }

    /**
     * Livro, número e, opcionalmente, mais referências depois de ";" (com ou
     * sem o livro).
     */
    private static function pattern(): string
    {
        if (self::$pattern !== null) {
            return self::$pattern;
        }

        $verse = '\d{1,3}(?:\s?[.:]\s?\d{1,3})?';
        $range = $verse.'(?:\s?[-–—]\s?'.$verse.')?';
        // ",18" ou ",9-11" (versículos do mesmo capítulo); ", 6.23" não entra.
        $numbers = $range.'(?:\s?,\s?\d{1,3}(?:\s?[-–—]\s?\d{1,3})?(?![.:]\d))*(?![\d\p{L}]|[.:]\d)';
        $reference = '(?:'.self::bookPattern().')\.?\s?'.$numbers;

        return self::$pattern = '/(?<![\p{L}\d])'.$reference.'(?:\s*;\s*(?:(?:'.self::bookPattern().')\.?\s?)?'.$numbers.')*/u';
    }

    /** Nomes e abreviações dos livros, dos mais longos aos mais curtos. */
    private static function bookPattern(): string
    {
        if (self::$bookPattern !== null) {
            return self::$bookPattern;
        }

        $names = [];

        foreach (BibleBook::cases() as $book) {
            foreach ([$book->label(), ...$book->aliases()] as $name) {
                $names[] = $name;
            }
        }

        $names = array_unique($names);
        usort($names, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        $alternatives = array_map(function (string $name) {
            // "1Coríntios" também casa "1 Coríntios"; espaços internos são flexíveis.
            $name = (string) preg_replace('/^([1-3]|I{1,3})(?=\p{L})/u', '$1 ', $name);

            return implode('\s?', array_map(
                fn (string $word) => preg_quote($word, '/'),
                preg_split('/\s+/u', $name) ?: [$name],
            ));
        }, $names);

        return self::$bookPattern = implode('|', $alternatives);
    }
}
