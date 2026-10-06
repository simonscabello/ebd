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
 * marcações, sem as citações de versículos ("(Mt 12.38-40; Jo 3.16)" e
 * "cf. Jo 2.19" somem: em voz alta uma lista de referências só atrapalha),
 * com as referências que fazem parte da frase por extenso ("o Salmo 23")
 * e com os títulos separados do texto para a narração fazer pausa. Também divide o texto em trechos que
 * cabem no limite da API, de preferência nas seções e nos parágrafos.
 */
final readonly class StudyNarration
{
    /** Delimitam cada referência bíblica (já por extenso) até clean() decidir se ela fica. */
    private const REF_START = "\u{E000}";

    private const REF_END = "\u{E001}";

    /** Nível do título da lição: é título, mas nunca abre uma parte. */
    private const TITLE_LEVEL = 9;

    /**
     * @param  list<array{level: int|null, text: string}>  $blocks  level: nível do título (null = texto)
     */
    private function __construct(private array $blocks) {}

    public static function make(?string $title, ?string $markdown): self
    {
        $blocks = [];

        if (filled($title)) {
            $blocks[] = ['level' => self::TITLE_LEVEL, 'text' => self::sentence(self::clean((string) $title))];
        }

        if (filled($markdown)) {
            $html = Str::markdown((string) $markdown, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 20,
            ]);

            $html = BibleLinks::replace(
                $html,
                fn (string $written, Reference $reference) => self::REF_START.e(self::speakReference($reference)).self::REF_END,
            );

            $document = new DOMDocument;
            @$document->loadHTML('<?xml encoding="utf-8"?><div id="narration">'.$html.'</div>', LIBXML_NOERROR | LIBXML_NONET);

            $root = $document->getElementById('narration');

            if ($root !== null) {
                self::collect($root, $blocks);
            }
        }

        return new self(array_values(array_filter($blocks, fn (array $block) => (bool) preg_match('/\p{L}/u', $block['text']))));
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
     * Partes do estudo, uma por narrador: o que vem antes do primeiro tópico
     * principal (com o título da lição), cada tópico principal com os seus
     * subtópicos e a conclusão. Tópico principal é o nível de título mais alto
     * que se repete; sem ele, o estudo é uma parte só.
     *
     * @return list<list<array{level: int|null, text: string}>>
     */
    public function parts(): array
    {
        $levels = array_count_values(array_filter(
            array_map(fn (array $block) => $block['level'], $this->blocks),
            fn (?int $level) => $level !== null && $level !== self::TITLE_LEVEL,
        ));
        ksort($levels);
        $top = array_key_first(array_filter($levels, fn (int $count) => $count >= 2));

        $parts = [];
        $current = [];

        foreach ($this->blocks as $block) {
            if ($top !== null && $block['level'] === $top && $current !== []) {
                $parts[] = $current;
                $current = [];
            }

            $current[] = $block;
        }

        if ($current !== []) {
            $parts[] = $current;
        }

        // Só o título antes do primeiro tópico: quem lê o tópico lê o título.
        if (count($parts) > 1 && count($parts[0]) === 1 && $parts[0][0]['level'] === self::TITLE_LEVEL) {
            array_unshift($parts[1], $parts[0][0]);
            array_shift($parts);
        }

        return $parts;
    }

    /**
     * Trechos de até $maxChars caracteres, marcados com a parte (narrador) a
     * que pertencem; um trecho nunca junta duas partes. Uma seção nova começa
     * trecho novo quando o atual já passou da metade; parágrafos nunca são
     * cortados, a não ser que sozinhos passem do limite (aí o corte é por frase).
     *
     * @return list<array{part: int, text: string}>
     */
    public function chunks(int $maxChars): array
    {
        $chunks = [];

        foreach ($this->parts() as $part => $blocks) {
            $current = '';

            $flush = function () use (&$chunks, &$current, $part) {
                if (trim($current) !== '') {
                    $chunks[] = ['part' => $part, 'text' => trim($current)];
                }

                $current = '';
            };

            foreach ($blocks as $block) {
                if ($block['level'] !== null && mb_strlen($current) > $maxChars / 2) {
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
        }

        return $chunks;
    }

    /**
     * Texto de cada parte (ver parts()), na ordem.
     *
     * @return list<string>
     */
    public function partTexts(): array
    {
        return array_map(
            fn (array $blocks) => implode("\n\n", array_map(fn (array $block) => $block['text'], $blocks)),
            $this->parts(),
        );
    }

    /**
     * Divide um texto corrido (o roteiro de uma parte) em trechos de até
     * $maxChars, juntando parágrafos inteiros sempre que couberem.
     *
     * @return list<string>
     */
    public static function chunkText(string $text, int $maxChars): array
    {
        $chunks = [];
        $current = '';

        foreach (preg_split('/\n\s*\n/u', trim($text)) ?: [] as $paragraph) {
            foreach (self::split(trim((string) preg_replace('/\s+/u', ' ', $paragraph)), $maxChars) as $piece) {
                if ($piece === '') {
                    continue;
                }

                if ($current !== '' && mb_strlen($current) + 2 + mb_strlen($piece) > $maxChars) {
                    $chunks[] = $current;
                    $current = '';
                }

                $current .= ($current === '' ? '' : "\n\n").$piece;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

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
     * @param  list<array{level: int|null, text: string}>  $blocks
     */
    private static function collect(DOMNode $parent, array &$blocks): void
    {
        foreach ($parent->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                $text = self::clean((string) $node->textContent);

                if ($text !== '') {
                    $blocks[] = ['level' => null, 'text' => self::sentence($text)];
                }

                continue;
            }

            $tag = strtolower($node->tagName);

            match (true) {
                (bool) preg_match('/^h([1-6])$/', $tag, $heading) => $blocks[] = ['level' => (int) $heading[1], 'text' => self::sentence(self::clean((string) $node->textContent))],
                in_array($tag, ['ul', 'ol', 'blockquote', 'div', 'section', 'table', 'thead', 'tbody'], true) => self::collect($node, $blocks),
                in_array($tag, ['li', 'tr'], true) && self::hasBlockChildren($node) => self::collect($node, $blocks),
                $tag === 'tr' => $blocks[] = ['level' => null, 'text' => self::sentence(self::clean(implode(', ', array_map(
                    fn (DOMNode $cell) => (string) $cell->textContent,
                    iterator_to_array($node->childNodes),
                ))))],
                $tag === 'hr', $tag === 'img' => null,
                default => $blocks[] = ['level' => null, 'text' => self::sentence(self::clean((string) $node->textContent))],
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
        $text = self::dropCitations($text);
        $text = (string) preg_replace('/\bcf\.\s*/iu', 'conforme ', $text);
        $text = (string) preg_replace('/\bvv\.\s*(?=\d)/u', 'versículos ', $text);
        $text = (string) preg_replace('/\bv\.\s*(?=\d)/u', 'versículo ', $text);
        $text = (string) preg_replace('/\ba\.\s?C\./u', 'antes de Cristo', $text);
        $text = (string) preg_replace('/\bd\.\s?C\./u', 'depois de Cristo', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Citações saem: parênteses só com referências ou números de versículo
     * ("(Mt 12.38-40; Jo 3.16)", "(cf. Jn 1.17)", "(v. 40)") e referências
     * depois de "cf.". As demais fazem parte da frase e ficam por extenso.
     */
    private static function dropCitations(string $text): string
    {
        $reference = self::REF_START.'[^'.self::REF_END.']*'.self::REF_END;
        $filler = '(?:'.$reference.'|[\s\d.,;:–—\-]|\be\b)';

        $text = (string) preg_replace('/\s*[(\[]\s*(?:(?:cf\.|conforme|veja|ver|vv?\.)\s*)?(?:'.$reference.'|\d)'.$filler.'*[)\]]/iu', '', $text);
        $text = (string) preg_replace('/,?\s*\b(?:cf\.|conforme)\s*'.$reference.'(?:\s*(?:[;,]|\be\b)\s*'.$reference.')*/iu', '', $text);
        $text = str_replace([self::REF_START, self::REF_END], '', $text);

        return (string) preg_replace('/\s+([,.;:!?…])/u', '$1', $text);
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
