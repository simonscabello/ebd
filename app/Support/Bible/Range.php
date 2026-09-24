<?php

namespace App\Support\Bible;

use App\Enums\BibleBook;

/**
 * Um trecho contínuo de uma referência: de um capítulo/versículo até outro.
 * `fromVerse` nulo significa capítulos inteiros (`toVerse` também é nulo).
 */
final readonly class Range
{
    public function __construct(
        public BibleBook $book,
        public int $fromChapter,
        public ?int $fromVerse,
        public int $toChapter,
        public ?int $toVerse,
    ) {}

    public function isWholeChapters(): bool
    {
        return $this->fromVerse === null;
    }

    public function isValid(): bool
    {
        if ($this->fromChapter < 1 || $this->toChapter < $this->fromChapter) {
            return false;
        }

        if ($this->isWholeChapters()) {
            return $this->toVerse === null;
        }

        if ($this->fromVerse < 1 || $this->toVerse === null || $this->toVerse < 1) {
            return false;
        }

        return $this->fromChapter !== $this->toChapter || $this->toVerse >= $this->fromVerse;
    }

    /** Parte numérica no formato canônico ("5.12-16", "23", "1.1-2.3"). */
    public function label(): string
    {
        if ($this->isWholeChapters()) {
            return $this->fromChapter === $this->toChapter
                ? (string) $this->fromChapter
                : "{$this->fromChapter}-{$this->toChapter}";
        }

        if ($this->fromChapter !== $this->toChapter) {
            return "{$this->fromChapter}.{$this->fromVerse}-{$this->toChapter}.{$this->toVerse}";
        }

        return $this->fromVerse === $this->toVerse
            ? "{$this->fromChapter}.{$this->fromVerse}"
            : "{$this->fromChapter}.{$this->fromVerse}-{$this->toVerse}";
    }
}
