<?php

namespace App\Support\Bible;

use App\Enums\BibleBook;

/**
 * Interpreta uma referência bíblica escrita à mão pelo professor.
 *
 * Aceita os formatos usados nas revistas e no dia a dia: "Lucas 5:12-16",
 * "Lc 5.12-16", "Sl 23", "Gn 1.1-2.3", "Mt 5.3-12; 6.9-13", "Jo 3.16,18",
 * "Rm 8.28-30; Jo 14.1-6", "1 Coríntios 13", com hífen ou travessão.
 * Devolve null quando não reconhece: o texto continua sendo exibido como foi
 * escrito, só sem o conteúdo do trecho.
 */
final readonly class Reference
{
    /**
     * @param  list<Range>  $ranges
     */
    private function __construct(public array $ranges) {}

    public static function parse(?string $text): ?self
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        $ranges = [];
        $book = null;

        foreach (explode(';', $text) as $segment) {
            if (! preg_match('/^\s*(?<book>(?:[1-3]|I{1,3})?\s*\p{L}[\p{L}\s.]*?)?\s*(?<rest>\d[\d\s.:,\-–—]*)$/u', $segment, $match)) {
                return null;
            }

            if ($match['book'] !== '') {
                $book = BibleBook::fromName($match['book']);
            }

            if ($book === null) {
                return null;
            }

            $parsed = self::parseSegment($book, $match['rest']);

            if ($parsed === null) {
                return null;
            }

            $ranges = [...$ranges, ...$parsed];
        }

        return $ranges === [] ? null : new self($ranges);
    }

    /**
     * Forma canônica ("Lucas 5.12-16; 6.9-13"), útil para confirmar como a
     * referência foi entendida.
     */
    public function label(): string
    {
        $parts = [];
        $book = null;

        foreach ($this->ranges as $range) {
            $parts[] = $range->book === $book
                ? $range->label()
                : $range->book->label().' '.$range->label();

            $book = $range->book;
        }

        return implode('; ', $parts);
    }

    /**
     * A parte numérica de um segmento: "5.12-16", "23", "1.1-2.3", "3.16,18", "5.3-5,9-11".
     *
     * @return list<Range>|null
     */
    private static function parseSegment(BibleBook $book, string $rest): ?array
    {
        $rest = (string) preg_replace('/\s+/u', '', $rest);
        $rest = str_replace(['–', '—', ':'], ['-', '-', '.'], $rest);

        $ranges = [];
        $chapter = null; // Capítulo corrente quando já houve "capítulo.versículo".

        foreach (explode(',', $rest) as $part) {
            $range = match (true) {
                // "23" ou, depois de um "c.v", o versículo "18" de "3.16,18".
                (bool) preg_match('/^(\d+)$/', $part, $m) => $chapter === null
                    ? new Range($book, (int) $m[1], null, (int) $m[1], null)
                    : new Range($book, $chapter, (int) $m[1], $chapter, (int) $m[1]),
                // "1-3" (capítulos) ou "9-11" (versículos, depois de um "c.v").
                (bool) preg_match('/^(\d+)-(\d+)$/', $part, $m) => $chapter === null
                    ? new Range($book, (int) $m[1], null, (int) $m[2], null)
                    : new Range($book, $chapter, (int) $m[1], $chapter, (int) $m[2]),
                // "3.16"
                (bool) preg_match('/^(\d+)\.(\d+)$/', $part, $m) => new Range($book, $chapter = (int) $m[1], (int) $m[2], (int) $m[1], (int) $m[2]),
                // "5.12-16"
                (bool) preg_match('/^(\d+)\.(\d+)-(\d+)$/', $part, $m) => new Range($book, $chapter = (int) $m[1], (int) $m[2], (int) $m[1], (int) $m[3]),
                // "1.1-2.3"
                (bool) preg_match('/^(\d+)\.(\d+)-(\d+)\.(\d+)$/', $part, $m) => new Range($book, $chapter = (int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4]),
                default => null,
            };

            if ($range === null || ! $range->isValid()) {
                return null;
            }

            $ranges[] = $range;
        }

        return $ranges;
    }
}
