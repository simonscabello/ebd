<?php

namespace Tests\Unit;

use App\Enums\LessonStatus;
use App\Queries\LibrarySearch;
use App\Support\Markdown;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LessonStatusTest extends TestCase
{
    /**
     * @return array<string, array{LessonStatus, LessonStatus, bool}>
     */
    public static function transitions(): array
    {
        return [
            'publicar rascunho' => [LessonStatus::Draft, LessonStatus::Published, true],
            'despublicar' => [LessonStatus::Published, LessonStatus::Draft, true],
            'rascunho para rascunho' => [LessonStatus::Draft, LessonStatus::Draft, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_lifecycle_transitions(LessonStatus $from, LessonStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public function test_only_published_is_visible(): void
    {
        $this->assertFalse(LessonStatus::Draft->isVisible());
        $this->assertTrue(LessonStatus::Published->isVisible());
        $this->assertSame([LessonStatus::Published], LessonStatus::visibleCases());
    }

    public function test_search_input_becomes_a_safe_prefix_tsquery(): void
    {
        // Termos repetidos são removidos; stopwords ("de") são descartadas pelo PostgreSQL.
        $this->assertSame('santidade:* & de:* & deus:*', LibrarySearch::toTsQuery('  Santidade de... Deus!! de'));
        $this->assertSame('lucas:* & 5:*', LibrarySearch::toTsQuery('Lucas 5'));
        $this->assertNull(LibrarySearch::toTsQuery("'&|!()"));
        $this->assertNull(LibrarySearch::toTsQuery(null));
    }

    public function test_markdown_headings_become_topics(): void
    {
        $this->assertSame(
            ['Introdução', 'O contexto'],
            Markdown::headings("# Título\n\n## Introdução\n\ntexto\n\n### O **contexto**\n"),
        );
    }
}
