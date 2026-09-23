<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Busca da biblioteca em PostgreSQL (full-text em português, sem acentos).
 */
class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $jovens;

    private Classroom $adultos;

    private Series $milagres;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jovens = Classroom::factory()->create(['name' => 'Jovens']);
        $this->adultos = Classroom::factory()->create(['name' => 'Adultos']);
        $this->milagres = Series::factory()->for($this->jovens)->create(['title' => 'Jornada dos Milagres de Jesus']);

        Lesson::factory()->forSeries($this->milagres)->completed()->on('2026-09-13')->create([
            'title' => 'Jesus Acalma a Tempestade',
            'bible_reference' => 'Marcos 4:35-41',
            'summary' => 'O vento e o mar lhe obedecem.',
            'content' => 'Os discípulos tiveram medo.',
        ]);
        Lesson::factory()->forSeries($this->milagres)->published()->on('2026-09-27')->create([
            'title' => 'A Santidade de Deus',
            'bible_reference' => 'Lucas 5:1-11',
            'summary' => 'Pedro reconhece quem é Jesus.',
            'content' => 'A reação de Pedro diante da pesca maravilhosa.',
        ]);
        Lesson::factory()->for($this->adultos)->completed()->on('2025-05-04')->create([
            'title' => 'Eu Sou o Pão da Vida',
            'bible_reference' => 'João 6:35-51',
            'summary' => 'Jesus sacia a fome da alma.',
            'content' => 'Discurso na sinagoga de Cafarnaum.',
        ]);
        Lesson::factory()->for($this->adultos)->create([
            'title' => 'Rascunho sobre santidade',
            'content' => 'santidade santidade',
        ]);
        Lesson::factory()->for($this->adultos)->completed()->membersOnly()->on('2026-09-20')->create([
            'title' => 'Viver é Cristo',
            'content' => 'Conteúdo restrito sobre santidade.',
        ]);
    }

    /**
     * @return list<string>
     */
    private function titles(string $query, ?User $user = null): array
    {
        $response = $user ? $this->actingAs($user)->get('/biblioteca?'.$query) : $this->get('/biblioteca?'.$query);
        $response->assertOk();

        return collect($response->viewData('page')['props']['results']['data'])->pluck('title')->all();
    }

    public function test_lists_visible_lessons_newest_first_without_drafts_or_restricted_content(): void
    {
        $this->assertSame(
            ['A Santidade de Deus', 'Jesus Acalma a Tempestade', 'Eu Sou o Pão da Vida'],
            $this->titles(''),
        );
    }

    public function test_searches_by_title_ignoring_accents_and_using_prefixes(): void
    {
        $this->assertSame(['A Santidade de Deus'], $this->titles('q=santid'));
        $this->assertSame(['Eu Sou o Pão da Vida'], $this->titles('q=pao+vida'));
    }

    public function test_searches_by_content_and_bible_reference(): void
    {
        $this->assertSame(['A Santidade de Deus'], $this->titles('q=pesca'));
        $this->assertSame(['Jesus Acalma a Tempestade'], $this->titles('q=Marcos+4'));
        $this->assertSame(['A Santidade de Deus'], $this->titles('q=lucas'));
    }

    public function test_searches_by_series_title(): void
    {
        $this->assertEqualsCanonicalizing(
            ['A Santidade de Deus', 'Jesus Acalma a Tempestade'],
            $this->titles('q=milagres'),
        );
    }

    public function test_highlights_matches_safely(): void
    {
        $response = $this->get('/biblioteca?q=pesca');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('results.data.0.headline', fn (string $headline) => str_contains($headline, '<mark>pesca</mark>')));
    }

    public function test_filters_by_classroom_series_and_year(): void
    {
        $this->assertSame(['Eu Sou o Pão da Vida'], $this->titles('classe='.$this->adultos->id));
        $this->assertSame(['A Santidade de Deus', 'Jesus Acalma a Tempestade'], $this->titles('serie='.$this->milagres->id));
        $this->assertSame(['Eu Sou o Pão da Vida'], $this->titles('ano=2025'));
    }

    public function test_members_find_restricted_lessons_of_their_classroom(): void
    {
        $member = User::factory()->studentOf($this->adultos)->create();

        $this->assertNotContains('Viver é Cristo', $this->titles('q=santidade'));
        $this->assertContains('Viver é Cristo', $this->titles('q=santidade', $member));
    }

    public function test_search_input_with_tsquery_syntax_does_not_break(): void
    {
        // Só símbolos: vira busca vazia (lista tudo), sem erro de sintaxe no PostgreSQL.
        $this->assertCount(3, $this->titles('q='.urlencode("'&|!():* <>")));
        $this->assertSame([], $this->titles('q='.urlencode("santidade') OR 1=1 --")));
    }
}
