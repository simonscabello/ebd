<?php

namespace Tests\Unit;

use App\Support\Push\PushMessage;
use PHPUnit\Framework\TestCase;

class PushMessageExcerptTest extends TestCase
{
    public function test_short_text_is_kept_and_whitespace_is_collapsed(): void
    {
        $this->assertSame('Resumo da lição nova.', PushMessage::excerpt("Resumo  da\nlição nova. "));
        $this->assertNull(PushMessage::excerpt(null));
        $this->assertNull(PushMessage::excerpt('   '));
    }

    public function test_long_text_keeps_the_first_sentence_when_it_fits(): void
    {
        $text = 'A relação de Deus com o ser humano sempre teve o objetivo de revelar quem Ele é. As Escrituras mostram que Deus se dá a conhecer aos poucos.';

        $this->assertSame('A relação de Deus com o ser humano sempre teve o objetivo de revelar quem Ele é.', PushMessage::excerpt($text));
    }

    public function test_without_a_short_sentence_it_cuts_at_a_word_boundary(): void
    {
        $text = 'Uma frase longa sem ponto que segue falando sobre a santidade de Deus e a resposta humana diante dela até ultrapassar o limite definido';

        $excerpt = PushMessage::excerpt($text, 60);

        $this->assertSame('Uma frase longa sem ponto que segue falando sobre a…', $excerpt);
        $this->assertLessThanOrEqual(61, mb_strlen($excerpt));
    }
}
