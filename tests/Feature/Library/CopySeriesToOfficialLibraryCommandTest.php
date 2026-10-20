<?php

namespace Tests\Feature\Library;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\Series;
use App\Models\User;
use App\Support\OfficialLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Levar um estudo da igreja do dono para a biblioteca oficial: cópia (a
 * original fica intacta), com todas as lições em rascunho, para revisar.
 */
class CopySeriesToOfficialLibraryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_copies_the_series_with_drafts_and_leaves_the_original_alone(): void
    {
        $classroom = Classroom::factory()->create();
        User::factory()->admin()->create();
        $series = Series::factory()->for($classroom)->create(['title' => 'Fora de Contexto']);
        $draft = Lesson::factory()->for($classroom)->forSeries($series)->number(1)->create(['title' => 'Jeremias 29.11', 'content' => 'Estudo.']);
        LessonBlock::factory()->for($draft)->create(['body' => 'Bloco.']);
        Lesson::factory()->for($classroom)->forSeries($series)->number(2)->published()->create(['title' => 'Filipenses 4.13']);

        $this->artisan('didache:copiar-para-biblioteca', ['series' => $series->id])
            ->expectsOutputToContain('versículos da NAA')
            ->expectsConfirmation('Copiar "Fora de Contexto" (2 lições) para a biblioteca oficial?', 'yes')
            ->assertSuccessful();

        $copy = Series::query()->where('classroom_id', OfficialLibrary::classroom()->id)->sole();
        $this->assertSame('Fora de Contexto', $copy->title);
        $this->assertNull($copy->source_series_id);
        $this->assertSame(['Jeremias 29.11', 'Filipenses 4.13'], $copy->lessons()->orderBy('number')->pluck('title')->all());
        $this->assertSame(['draft', 'draft'], $copy->lessons()->orderBy('number')->get()->map(fn ($l) => $l->status->value)->all());
        $this->assertSame(['Bloco.'], $copy->lessons()->where('number', 1)->sole()->blocks()->pluck('body')->all());

        $this->assertSame(2, $series->lessons()->count());
        $this->assertSame($classroom->id, $series->fresh()->classroom_id);
    }

    public function test_it_refuses_a_series_that_is_already_official(): void
    {
        User::factory()->admin()->create();
        $series = Series::factory()->for(OfficialLibrary::classroom())->create();

        $this->artisan('didache:copiar-para-biblioteca', ['series' => $series->id])->assertFailed();
    }

    public function test_declining_copies_nothing(): void
    {
        $classroom = Classroom::factory()->create();
        User::factory()->admin()->create();
        $series = Series::factory()->for($classroom)->create(['title' => 'Fora de Contexto']);

        $this->artisan('didache:copiar-para-biblioteca', ['series' => $series->id])
            ->expectsConfirmation('Copiar "Fora de Contexto" (0 lições) para a biblioteca oficial?', 'no')
            ->assertFailed();

        $this->assertSame(1, Series::query()->count());
    }

    public function test_it_records_the_origin_and_refuses_a_second_copy_unless_asked(): void
    {
        $classroom = Classroom::factory()->create();
        User::factory()->admin()->create();
        $series = Series::factory()->for($classroom)->create(['title' => 'Fora de Contexto']);
        $question = 'Copiar "Fora de Contexto" (0 lições) para a biblioteca oficial?';

        $this->artisan('didache:copiar-para-biblioteca', ['series' => $series->id])
            ->expectsConfirmation($question, 'yes')
            ->assertSuccessful();

        $copy = Series::query()->where('library_origin_series_id', $series->id)->sole();
        $this->assertSame(OfficialLibrary::classroom()->id, $copy->classroom_id);

        $this->artisan('didache:copiar-para-biblioteca', ['series' => $series->id])
            ->expectsOutputToContain("já foi copiada para a biblioteca (id {$copy->id})")
            ->assertFailed();
        $this->assertSame(1, Series::query()->where('library_origin_series_id', $series->id)->count());

        $this->artisan('didache:copiar-para-biblioteca', ['series' => $series->id, '--de-novo' => true])
            ->expectsConfirmation($question, 'yes')
            ->assertSuccessful();
        $this->assertSame(2, Series::query()->where('library_origin_series_id', $series->id)->count());
    }
}
