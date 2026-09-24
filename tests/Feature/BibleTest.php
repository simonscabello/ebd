<?php

namespace Tests\Feature;

use App\Enums\BibleBook;
use App\Models\BibleVerse;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\User;
use App\Support\Bible\Bible;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeBible;
use Tests\TestCase;

class BibleTest extends TestCase
{
    use RefreshDatabase;

    public function test_passage_returns_the_verses_of_a_reference_in_order(): void
    {
        FakeBible::seed([BibleBook::Luke->value => [5 => 16, 6 => 5], BibleBook::Romans->value => [8 => 30]]);

        $passage = Bible::passage('Rm 8.28-30; Lc 5.15-6.2');

        $this->assertNotNull($passage);
        $this->assertSame('Romanos 8.28-30; Lucas 5.15-6.2', $passage['label']);
        $this->assertSame('NAA', $passage['version']);
        $this->assertSame(
            ['Romanos 8.28', 'Romanos 8.29', 'Romanos 8.30', 'Lucas 5.15', 'Lucas 5.16', 'Lucas 6.1', 'Lucas 6.2'],
            array_column($passage['verses'], 'text'),
        );
        $this->assertSame(['chapter' => 8, 'verse' => 28, 'text' => 'Romanos 8.28'], $passage['verses'][0]);
    }

    public function test_whole_chapters_and_missing_verses_are_handled(): void
    {
        FakeBible::seed([BibleBook::Psalms->value => [23 => 6, 24 => 10]]);
        // Versículo que a versão não tem (fica em nota de rodapé em algumas edições).
        BibleVerse::query()->where('chapter', 23)->where('verse', 3)->delete();

        $this->assertCount(15, Bible::passage('Sl 23-24')['verses'] ?? []);
        $this->assertSame([1, 2, 4, 5, 6], array_column(Bible::passage('Sl 23')['verses'] ?? [], 'verse'));
    }

    public function test_passage_is_null_when_unknown_or_not_imported(): void
    {
        $this->assertNull(Bible::passage('Sl 23'), 'Sem texto importado, nada é devolvido.');

        FakeBible::seed([BibleBook::Psalms->value => [23 => 6]]);

        $this->assertNull(Bible::passage('Sl 150'));
        $this->assertNull(Bible::passage('Leia o texto base'));
        $this->assertNull(Bible::passage(null));
    }

    public function test_lesson_page_and_readings_carry_the_text(): void
    {
        FakeBible::seed([BibleBook::Luke->value => [5 => 16], BibleBook::Isaiah->value => [6 => 8]]);

        $lesson = Lesson::factory()->published()->for(Classroom::factory()->create())->create(['bible_reference' => 'Lucas 5:12-16']);
        LessonReading::factory()->for($lesson)->create(['reference' => 'Isaías 6.1-8', 'position' => 1]);
        LessonReading::factory()->for($lesson)->create(['reference' => 'Revista, páginas 10 a 12', 'position' => 2]);

        $this->get("/licoes/{$lesson->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lesson.bible_passage.label', 'Lucas 5.12-16')
                ->where('lesson.bible_passage.verses.0.text', 'Lucas 5.12')
                ->has('lesson.bible_passage.verses', 5)
                ->where('lesson.readings.0.passage.label', 'Isaías 6.1-8')
                ->has('lesson.readings.0.passage.verses', 8)
                ->where('lesson.readings.1.passage', null)
            );
    }

    public function test_teachers_can_preview_a_reference(): void
    {
        FakeBible::seed([BibleBook::John->value => [3 => 20]]);
        $teacher = User::factory()->teacherOf(Classroom::factory()->create())->create();

        $this->actingAs($teacher)
            ->getJson('/admin/biblia/previa?reference=Jo+3.16')
            ->assertOk()
            ->assertJsonPath('passage.label', 'João 3.16')
            ->assertJsonPath('passage.verses.0.text', 'João 3.16');

        $this->actingAs($teacher)
            ->getJson('/admin/biblia/previa?reference=Nada')
            ->assertOk()
            ->assertJsonPath('passage', null);

        $this->actingAs($teacher)->getJson('/admin/biblia/previa')->assertUnprocessable();

        $student = User::factory()->studentOf(Classroom::factory()->create())->create();
        $this->actingAs($student)->getJson('/admin/biblia/previa?reference=Jo+3.16')->assertForbidden();
    }

    public function test_import_command_loads_the_json_and_is_idempotent(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bible');
        FakeBible::json($path);

        $this->artisan('bible:import', ['path' => $path, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(66 * 2 * 2, BibleVerse::query()->count());
        $this->assertSame('Apocalipse 2.2', Bible::passage('Ap 2.2')['verses'][0]['text'] ?? null);

        // Reimportar atualiza o texto sem duplicar linhas.
        FakeBible::json($path, chaptersPerBook: 3);
        $this->artisan('bible:import', ['path' => $path, '--force' => true])->assertSuccessful();

        $this->assertSame(66 * 3 * 2, BibleVerse::query()->count());

        // --fresh remove o que não está mais no arquivo.
        FakeBible::json($path, chaptersPerBook: 1);
        $this->artisan('bible:import', ['path' => $path, '--force' => true, '--fresh' => true])->assertSuccessful();

        $this->assertSame(66 * 1 * 2, BibleVerse::query()->count());

        unlink($path);
    }

    public function test_import_command_removes_the_space_before_punctuation(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bible');
        FakeBible::json($path);

        // Palavras em versalete na edição impressa deixam "Senhor ," no arquivo.
        $books = json_decode((string) file_get_contents($path), true);
        $books[0]['chapters'][0][0] = '  O Senhor , porém, disse: — Eu Sou o Que Sou . Que é isso ? ';
        file_put_contents($path, json_encode($books, JSON_UNESCAPED_UNICODE));

        $this->artisan('bible:import', ['path' => $path, '--force' => true])->assertSuccessful();

        $this->assertSame(
            'O Senhor, porém, disse: — Eu Sou o Que Sou. Que é isso?',
            Bible::passage('Gn 1.1')['verses'][0]['text'] ?? null,
        );

        unlink($path);
    }

    public function test_import_command_refuses_files_out_of_order_and_asks_before_writing(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bible');
        FakeBible::json($path);

        $this->artisan('bible:import', ['path' => $path])
            ->expectsConfirmation('Importar para este banco?', 'no')
            ->assertFailed();

        $this->assertSame(0, BibleVerse::query()->count());

        $books = json_decode((string) file_get_contents($path), true);
        [$books[0], $books[1]] = [$books[1], $books[0]];
        file_put_contents($path, json_encode($books));

        $this->artisan('bible:import', ['path' => $path, '--force' => true])
            ->expectsOutputToContain('não corresponde a Gênesis')
            ->assertFailed();

        $this->artisan('bible:import', ['path' => '/caminho/inexistente.json', '--force' => true])->assertFailed();

        unlink($path);
    }
}
