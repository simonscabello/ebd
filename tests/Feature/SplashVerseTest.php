<?php

namespace Tests\Feature;

use App\Models\BibleVerse;
use App\Support\Bible\Reference;
use App\Support\SplashVerse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A tela de abertura do app mostra um versículo curto (ou uma mensagem,
 * quando o texto bíblico ainda não foi importado).
 */
class SplashVerseTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_reference_is_a_single_recognized_verse(): void
    {
        foreach (SplashVerse::REFERENCES as $reference) {
            $parsed = Reference::parse($reference);

            $this->assertNotNull($parsed, $reference);
            $this->assertCount(1, $parsed->ranges, $reference);
        }
    }

    public function test_shows_a_message_when_the_bible_is_not_imported(): void
    {
        $this->assertSame(['text' => SplashVerse::FALLBACK, 'reference' => null], SplashVerse::pick());

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('id="splash"', false)
            ->assertSee(SplashVerse::FALLBACK);
    }

    public function test_shows_the_verse_text_with_its_reference(): void
    {
        foreach (SplashVerse::REFERENCES as $reference) {
            $range = Reference::parse($reference)->ranges[0];

            BibleVerse::query()->create([
                'book' => $range->book,
                'chapter' => $range->fromChapter,
                'verse' => $range->fromVerse,
                'text' => "Palavra do Senhor, {$reference}.",
            ]);
        }

        $splash = SplashVerse::pick();

        $this->assertMatchesRegularExpression('/^Palavra do Senhor, .+\.$/u', $splash['text']);
        $this->assertStringEndsWith(' · NAA', (string) $splash['reference']);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Palavra do Senhor, ')
            ->assertSee(' · NAA')
            ->assertDontSee(SplashVerse::FALLBACK);
    }
}
