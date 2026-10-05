<?php

namespace App\Support\Audio;

use App\Support\Bible\BibleLinks;
use App\Support\Bible\Range;
use App\Support\Bible\Reference;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;

/**
 * Transforma o Markdown do estudo em texto para ser lido em voz alta: sem
 * marcações, com as referências bíblicas por extenso ("Mt 12.38-40" vira
 * "Mateus, capítulo 12, versículos 38 a 40") e com os títulos separados do
 * texto para a narração fazer pausa. Também divide o texto em trechos que
 * cabem no limite da API, de preferência nas seções e nos parágrafos.
 */
final readonly class StudyNarration
{
    /**
     * @param  list<array{heading: bool, text: string}>  $blocks
     */
    private function __construct(private array $blocks) {}

    public static function make(?string $title, ?string $markdown): self
    {
        $blocks = [];

        if (filled($title)) {
            $blocks[] = ['heading' => true, 'text' => self::sentence(self::clean((string) $title))];
        }

        if (filled($markdown)) {
            $html = Str::markdown((string) $markdown, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 20,
            ]);

            $html = BibleLinks::replace($html, fn (string $written, Reference $reference) => e(self::speakReference($reference)));

            $document = new DOMDocument;
            @$document->loadHTML('<?xml encoding="utf-8"?><div id="narration">'.$html.'</div>', LIBXML_NOERROR | LIBXML_NONET);

            $root = $document->getElementById('narration');

            if ($root !== null) {
                self::collect($root, $blocks);
            }
        }

        return new self(array_values(array_filter($blocks, fn (array $block) => $block['text'] !== '')));
    }

    public function isEmpty(): bool
    {
        return $this->blocks === [];
    }

    /** Texto inteiro, com linhas em branco em volta dos títulos. */
    public function text(): string
    {
        return implode("\n\n", array_map(fn (array $block) => $block['text'], $this->blocks));
    }

    /**
     * Trechos de até $maxChars caracteres. Uma seção nova começa trecho novo
     * quando o atual já passou da metade; parágrafos nunca são cortados, a
     * não ser que sozinhos passem do limite (aí o corte é por frase).
     *
     * @return list<string>
     */
    public function chunks(int $maxChars): array
    {
        $chunks = [];
        $current = '';

        $flush = function () use (&$chunks, &$current) {
            if (trim($current) !== '') {
                $chunks[] = trim($current);
            }

            $current = '';
        };

        foreach ($this->blocks as $block) {
            if ($block['heading'] && mb_strlen($current) > $maxChars / 2) {
                $flush();
            }

            foreach (self::split($block['text'], $maxChars) as $piece) {
                if ($current !== '' && mb_strlen($current) + 2 + mb_strlen($piece) > $maxChars) {
                    $flush();
                }

                $current .= ($current === '' ? '' : "\n\n").$piece;
            }
        }

        $flush();

        return $chunks;
    }

    /**
     * "Mateus, capítulo 12, versículos 38 a 40"; "Salmo 23";
     * "João, capítulo 3, versículo 16, e versículo 18".
     */
    public static function speakReference(Reference $reference): string
    {
        $parts = [];
        $book = null;
        $chapter = null;

        foreach ($reference->ranges as $range) {
            $words = [];

            if ($range->book !== $book) {
                $words[] = self::speakBook($range->book->label());
                $chapter = null;
            }

            $words[] = self::speakRange($range, $chapter);
            $parts[] = implode(', ', array_filter($words));

            $book = $range->book;
            $chapter = $range->toChapter;
        }

        // Salmos se lê "Salmo 23", não "Salmos, capítulo 23".
        return str_replace(['Salmos, capítulo ', 'Salmos, capítulos '], ['Salmo ', 'Salmos '], implode(', e ', $parts));
    }

    private static function speakRange(Range $range, ?int $currentChapter): string
    {
        if ($range->isWholeChapters()) {
            return $range->fromChapter === $range->toChapter
                ? "capítulo {$range->fromChapter}"
                : "capítulos {$range->fromChapter} a {$range->toChapter}";
        }

        if ($range->fromChapter !== $range->toChapter) {
            return "capítulo {$range->fromChapter}, versículo {$range->fromVerse}, até o capítulo {$range->toChapter}, versículo {$range->toVerse}";
        }

        $verses = $range->fromVerse === $range->toVerse
            ? "versículo {$range->fromVerse}"
            : "versículos {$range->fromVerse} a {$range->toVerse}";

        return $range->fromChapter === $currentChapter ? $verses : "capítulo {$range->fromChapter}, {$verses}";
    }

    /** "1Coríntios" vira "Primeira Coríntios"; "2Reis", "Segundo Reis". */
    private static function speakBook(string $label): string
    {
        if (! preg_match('/^([1-3])\s*(.+)$/u', $label, $match)) {
            return $label;
        }

        $feminine = in_array($match[2], ['Coríntios', 'Tessalonicenses', 'Pedro', 'João'], true);
        $ordinal = [
            '1' => $feminine ? 'Primeira' : 'Primeiro',
            '2' => $feminine ? 'Segunda' : 'Segundo',
            '3' => $feminine ? 'Terceira' : 'Terceiro',
        ][$match[1]];

        return "{$ordinal} {$match[2]}";
    }

    /**
     * Percorre os blocos do HTML: títulos, parágrafos, itens de lista,
     * citações e tabelas viram blocos de texto separados.
     *
     * @param  list<array{heading: bool, text: string}>  $blocks
     */
    private static function collect(DOMNode $parent, array &$blocks): void
    {
        foreach ($parent->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                $text = self::clean((string) $node->textContent);

                if ($text !== '') {
                    $blocks[] = ['heading' => false, 'text' => self::sentence($text)];
                }

                continue;
            }

            $tag = strtolower($node->tagName);

            match (true) {
                (bool) preg_match('/^h[1-6]$/', $tag) => $blocks[] = ['heading' => true, 'text' => self::sentence(self::clean((string) $node->textContent))],
                in_array($tag, ['ul', 'ol', 'blockquote', 'div', 'section', 'table', 'thead', 'tbody'], true) => self::collect($node, $blocks),
                in_array($tag, ['li', 'tr'], true) && self::hasBlockChildren($node) => self::collect($node, $blocks),
                $tag === 'tr' => $blocks[] = ['heading' => false, 'text' => self::sentence(self::clean(implode(', ', array_map(
                    fn (DOMNode $cell) => (string) $cell->textContent,
                    iterator_to_array($node->childNodes),
                ))))],
                $tag === 'hr', $tag === 'img' => null,
                default => $blocks[] = ['heading' => false, 'text' => self::sentence(self::clean((string) $node->textContent))],
            };
        }
    }

    private static function hasBlockChildren(DOMElement $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['p', 'ul', 'ol', 'blockquote', 'div'], true)) {
                return true;
            }
        }

        return false;
    }

    /** Tira sobras de marcação e abreviações que soam mal em voz alta. */
    private static function clean(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[*_`#>|~]+/u', ' ', $text);
        $text = (string) preg_replace('/\bcf\.\s*/iu', 'conforme ', $text);
        $text = (string) preg_replace('/\bvv\.\s*(?=\d)/u', 'versículos ', $text);
        $text = (string) preg_replace('/\bv\.\s*(?=\d)/u', 'versículo ', $text);
        $text = (string) preg_replace('/\ba\.\s?C\./u', 'antes de Cristo', $text);
        $text = (string) preg_replace('/\bd\.\s?C\./u', 'depois de Cristo', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** Termina com pontuação, para a voz fechar a frase (títulos e itens de lista). */
    private static function sentence(string $text): string
    {
        if ($text === '' || preg_match('/[.!?…:;]["”’)\]]*$/u', $text)) {
            return $text;
        }

        return $text.'.';
    }

    /**
     * Divide um parágrafo grande demais por frases (e, em último caso, por palavras).
     *
     * @return list<string>
     */
    private static function split(string $text, int $maxChars): array
    {
        if (mb_strlen($text) <= $maxChars) {
            return [$text];
        }

        $pieces = [];
        $current = '';

        foreach (preg_split('/(?<=[.!?…])\s+/u', $text) ?: [$text] as $sentence) {
            foreach (mb_strlen($sentence) > $maxChars ? self::splitWords($sentence, $maxChars) : [$sentence] as $part) {
                if ($current !== '' && mb_strlen($current) + 1 + mb_strlen($part) > $maxChars) {
                    $pieces[] = $current;
                    $current = '';
                }

                $current .= ($current === '' ? '' : ' ').$part;
            }
        }

        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }

    /**
     * @return list<string>
     */
    private static function splitWords(string $text, int $maxChars): array
    {
        $pieces = [];
        $current = '';

        foreach (preg_split('/\s+/u', $text) ?: [] as $word) {
            if ($current !== '' && mb_strlen($current) + 1 + mb_strlen($word) > $maxChars) {
                $pieces[] = $current;
                $current = '';
            }

            $current .= ($current === '' ? '' : ' ').$word;
        }

        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }
}
