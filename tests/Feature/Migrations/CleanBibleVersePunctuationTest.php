<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O texto bíblico já importado perde o espaço antes da pontuação ("Senhor ,").
 */
class CleanBibleVersePunctuationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_09_24_000400_remove_space_before_punctuation_in_bible_verses.php';

    public function test_removes_the_space_before_punctuation_of_imported_verses(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();

        DB::table('bible_verses')->insert([
            ['book' => 1, 'chapter' => 4, 'verse' => 15, 'text' => 'O Senhor , porém, lhe disse: — Assim, qualquer que matar Caim será vingado sete vezes.'],
            ['book' => 2, 'chapter' => 3, 'verse' => 14, 'text' => 'Deus disse a Moisés: — Eu Sou o Que Sou . Disse mais: — Assim você dirá.'],
            ['book' => 27, 'chapter' => 5, 'verse' => 25, 'text' => 'E o que está escrito é isto: Mene , Mene , Tequel e Parsim .'],
            ['book' => 19, 'chapter' => 23, 'verse' => 1, 'text' => 'O Senhor é o meu pastor; nada me faltará.'],
        ]);

        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();

        $this->assertSame([
            'O Senhor, porém, lhe disse: — Assim, qualquer que matar Caim será vingado sete vezes.',
            'Deus disse a Moisés: — Eu Sou o Que Sou. Disse mais: — Assim você dirá.',
            'O Senhor é o meu pastor; nada me faltará.',
            'E o que está escrito é isto: Mene, Mene, Tequel e Parsim.',
        ], DB::table('bible_verses')->orderBy('book')->pluck('text')->all());
    }
}
