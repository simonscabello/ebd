<?php

namespace Tests\Unit;

use App\Support\Bible\BibleLinks;
use PHPUnit\Framework\TestCase;

class BibleLinksTest extends TestCase
{
    public function test_a_single_reference_becomes_a_button(): void
    {
        $this->assertSame(
            '<p>avanço (<button type="button" class="bible-ref" data-bible="Hebreus 2.4" aria-haspopup="dialog">Hb 2.4</button>).</p>',
            BibleLinks::link('<p>avanço (Hb 2.4).</p>'),
        );
    }

    public function test_references_in_sequence_share_the_whole_group(): void
    {
        $html = BibleLinks::link('<p>(At 5.12-13; At 8.6-8; 9.35,42; At 19.11-12)</p>');

        $this->assertSame(4, substr_count($html, 'data-bible="Atos 5.12-13|Atos 8.6-8|Atos 9.35; 9.42|Atos 19.11-12"'));
        $this->assertStringContainsString('>9.35,42</button>;</span> <button', $html);
    }

    public function test_numbered_books_and_whole_chapters_by_full_name(): void
    {
        $html = BibleLinks::link('<p>1 Coríntios 13.4-7, Salmo 23 e Atos 2.</p>');

        $this->assertStringContainsString('data-bible="1Coríntios 13.4-7"', $html);
        $this->assertStringContainsString('data-bible="Salmos 23"', $html);
        $this->assertStringContainsString('data-bible="Atos 2"', $html);
    }

    public function test_ordinary_text_is_left_alone(): void
    {
        foreach ([
            '<p>Os 12 apóstolos e Na 3 coisas.</p>',
            '<p>Exemplo 3, em 2020, Atos 2000.</p>',
            '<p><a href="https://example.com">Hb 2.4</a> e <code>Jo 3.16</code></p>',
        ] as $html) {
            $this->assertSame($html, BibleLinks::link($html));
        }
    }
}
