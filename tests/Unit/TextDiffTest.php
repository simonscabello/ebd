<?php

namespace Tests\Unit;

use App\Support\TextDiff;
use PHPUnit\Framework\TestCase;

class TextDiffTest extends TestCase
{
    public function test_equal_texts_are_all_same(): void
    {
        $this->assertSame(
            [['type' => 'same', 'text' => 'a'], ['type' => 'same', 'text' => 'b']],
            TextDiff::lines("a\nb", "a\nb"),
        );
    }

    public function test_a_changed_line_is_removed_then_added(): void
    {
        $this->assertSame(
            [
                ['type' => 'same', 'text' => 'a'],
                ['type' => 'removed', 'text' => 'b'],
                ['type' => 'added', 'text' => 'B'],
                ['type' => 'same', 'text' => 'c'],
            ],
            TextDiff::lines("a\nb\nc", "a\nB\nc"),
        );
    }

    public function test_null_against_text_is_all_added(): void
    {
        $this->assertSame([['type' => 'added', 'text' => 'novo']], TextDiff::lines(null, 'novo'));
        $this->assertSame([['type' => 'removed', 'text' => 'velho']], TextDiff::lines('velho', null));
        $this->assertSame([], TextDiff::lines(null, ''));
    }

    public function test_changed_reports_whether_anything_differs(): void
    {
        $this->assertFalse(TextDiff::changed("a\r\nb", "a\nb"));
        $this->assertTrue(TextDiff::changed('a', 'b'));
    }
}
