<?php

namespace Tests\Feature\Library;

use App\Actions\Library\CopySeriesToClassroom;
use App\Actions\Library\PublishSeriesVersion;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use App\Support\OfficialLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Versão nova de um estudo oficial: a cópia vê o aviso e a comparação, e
 * nada muda nela sozinho.
 */
class SeriesUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Series $official;

    private Lesson $second;

    private Series $copy;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        Classroom::factory()->create();
        $this->owner = User::factory()->admin()->create();
        $library = OfficialLibrary::classroom();
        $this->official = Series::factory()->for($library)->create(['title' => 'Fora de Contexto']);
        Lesson::factory()->for($library)->forSeries($this->official)->number(1)->published()->create(['title' => 'Primeira', 'content' => 'Igual.']);
        $this->second = Lesson::factory()->for($library)->forSeries($this->official)->number(2)->published()->create(['title' => 'Segunda', 'content' => "Linha um.\nLinha dois."]);
        $v1 = app(PublishSeriesVersion::class)->handle($this->owner, $this->official, null);

        $classroom = Classroom::factory()->for(Church::factory())->create();
        $this->teacher = User::factory()->teacherOf($classroom)->create();
        $this->copy = app(CopySeriesToClassroom::class)->handle($this->teacher, $v1, $classroom);
    }

    private function publishV2(): void
    {
        $this->second->update(['content' => "Linha um.\nLinha dois, revista."]);
        Lesson::factory()->for($this->official->classroom)->forSeries($this->official)->number(3)->published()->create(['title' => 'Terceira']);
        app(PublishSeriesVersion::class)->handle($this->owner, $this->official, 'Revisão da lição 2.');
    }

    public function test_without_a_new_version_there_is_no_notice(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('admin.series.edit', $this->copy))
            ->assertInertia(fn (Assert $page) => $page->where('update_available', null));
    }

    public function test_a_new_version_shows_the_notice_and_the_comparison_without_changing_the_copy(): void
    {
        $before = $this->copy->lessons()->orderBy('number')->pluck('content')->all();
        $this->publishV2();

        $this->actingAs($this->teacher)
            ->get(route('admin.series.edit', $this->copy))
            ->assertInertia(fn (Assert $page) => $page->where('update_available', ['from' => 1, 'to' => 2]));

        $this->actingAs($this->teacher)
            ->get(route('admin.series.changes', $this->copy))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/series/update')
                ->where('comparison.from', 1)
                ->where('comparison.to', 2)
                ->where('comparison.notes', ['Revisão da lição 2.'])
                ->has('comparison.lessons', 2)
                ->where('comparison.lessons.0.title', 'Segunda')
                ->where('comparison.lessons.0.status', 'changed')
                ->where('comparison.lessons.0.fields.0.name', 'content')
                ->where('comparison.lessons.0.fields.0.diff', [
                    ['type' => 'same', 'text' => 'Linha um.'],
                    ['type' => 'removed', 'text' => 'Linha dois.'],
                    ['type' => 'added', 'text' => 'Linha dois, revista.'],
                ])
                ->where('comparison.lessons.1.title', 'Terceira')
                ->where('comparison.lessons.1.status', 'added'));

        $this->assertSame($before, $this->copy->lessons()->orderBy('number')->pluck('content')->all());
    }

    public function test_marking_as_seen_clears_the_notice_until_the_next_version(): void
    {
        $this->publishV2();
        $before = $this->copy->lessons()->orderBy('number')->pluck('content')->all();

        $this->actingAs($this->teacher)
            ->post(route('admin.series.changes.acknowledge', $this->copy))
            ->assertRedirect(route('admin.series.edit', $this->copy));

        $this->assertSame(2, $this->copy->fresh()->source_version);
        $this->assertSame($before, $this->copy->lessons()->orderBy('number')->pluck('content')->all());
        $this->actingAs($this->teacher)->get(route('admin.series.edit', $this->copy))
            ->assertInertia(fn (Assert $page) => $page->where('update_available', null));

        app(PublishSeriesVersion::class)->handle($this->owner, $this->official, null);
        $this->actingAs($this->teacher)->get(route('admin.series.edit', $this->copy))
            ->assertInertia(fn (Assert $page) => $page->where('update_available', ['from' => 2, 'to' => 3]));
    }

    public function test_other_churches_and_series_without_origin_get_no_comparison(): void
    {
        $this->publishV2();
        $outsider = User::factory()->teacherOf(Classroom::factory()->for(Church::factory())->create())->create();

        $this->actingAs($outsider)->get(route('admin.series.changes', $this->copy))->assertForbidden();
        $this->actingAs($outsider)->post(route('admin.series.changes.acknowledge', $this->copy))->assertForbidden();

        $own = Series::factory()->for($this->copy->classroom)->create();
        $this->actingAs($this->teacher)->get(route('admin.series.changes', $own))->assertNotFound();
    }

    public function test_the_series_list_flags_copies_with_a_new_version(): void
    {
        $this->publishV2();

        $this->actingAs($this->teacher)
            ->get(route('admin.series.index'))
            ->assertInertia(fn (Assert $page) => $page->where('series.0.update_available', ['from' => 1, 'to' => 2]));
    }
}
