<?php

namespace Tests\Feature\Library;

use App\Mcp\Servers\EbdServer;
use App\Mcp\Tools\ListClassrooms;
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
 * A biblioteca oficial não é uma turma para o dono: some das listas de
 * turmas, lições e séries e ganha uma área própria em /dono/biblioteca.
 */
class OfficialLibraryAreaTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Classroom $jovens;

    private Series $official;

    private Lesson $draft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jovens = Classroom::factory()->create(['name' => 'Jovens']);
        $this->owner = User::factory()->admin()->create();
        $library = OfficialLibrary::classroom();
        $this->official = Series::factory()->for($library)->create(['title' => 'Fora de Contexto']);
        $this->draft = Lesson::factory()->for($library)->forSeries($this->official)->number(1)->create(['title' => 'Jeremias 29.11']);
        Lesson::factory()->for($library)->forSeries($this->official)->number(2)->published()->create(['title' => 'Filipenses 4.13']);
        Lesson::factory()->for($this->jovens)->published()->create(['title' => 'Lição dos Jovens']);
    }

    public function test_the_library_is_not_listed_as_a_classroom_for_the_owner(): void
    {
        $this->actingAs($this->owner)->get('/admin/classes')
            ->assertInertia(fn (Assert $page) => $page->where('classrooms', fn ($items) => collect($items)->pluck('name')->all() === ['Jovens']));

        $this->actingAs($this->owner)->get('/admin')
            ->assertInertia(fn (Assert $page) => $page->where('classrooms', fn ($items) => collect($items)->pluck('name')->all() === ['Jovens']));

        $this->actingAs($this->owner)->get('/admin/licoes')
            ->assertInertia(fn (Assert $page) => $page->where('lessons.data', fn ($items) => collect($items)->pluck('title')->all() === ['Lição dos Jovens']));

        $this->actingAs($this->owner)->get('/admin/series')
            ->assertInertia(fn (Assert $page) => $page->where('series', fn ($items) => collect($items)->doesntContain('title', 'Fora de Contexto')));

        $this->actingAs($this->owner)->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('classrooms', fn ($items) => collect($items)->doesntContain('name', 'Estudos oficiais')));

        $this->actingAs($this->owner)->get('/biblioteca')
            ->assertInertia(fn (Assert $page) => $page->where('results.data', fn ($items) => collect($items)->doesntContain('title', 'Filipenses 4.13')));
    }

    public function test_the_owner_manages_the_library_in_its_own_area(): void
    {
        $this->actingAs($this->owner)->get('/dono/biblioteca')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('owner/library')
                ->where('classroom_id', OfficialLibrary::classroom()->id)
                ->where('series.0.title', 'Fora de Contexto')
                ->where('series.0.lessons.0.title', 'Lição 1 — Jeremias 29.11')
                ->where('series.0.lessons.0.status', 'draft')
                ->where('series.0.lessons.1.status', 'published')
                ->where('series.0.latest_version', null));

        // Editar uma lição oficial continua pelas telas de sempre.
        $this->actingAs($this->owner)->get(route('admin.lessons.edit', $this->draft))->assertOk();
    }

    public function test_only_the_owner_opens_the_library_area(): void
    {
        $admin = User::factory()->create();
        Church::factory()->create()->admins()->attach($admin);

        $this->actingAs($admin)->get('/dono/biblioteca')->assertForbidden();
    }

    public function test_mcp_names_the_library_clearly(): void
    {
        EbdServer::actingAs($this->owner)
            ->tool(ListClassrooms::class)
            ->assertSee('Biblioteca oficial');
    }
}
