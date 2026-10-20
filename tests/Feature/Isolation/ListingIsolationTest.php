<?php

namespace Tests\Feature\Isolation;

use App\Models\Church;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Início e biblioteca mostram a igreja de quem está vendo. Lição pública
 * continua abrindo pelo link.
 */
class ListingIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classB;

    private Church $churchB;

    protected function setUp(): void
    {
        parent::setUp();

        $classA = Classroom::factory()->create(['slug' => 'turma-a']);
        Lesson::factory()->for($classA)->published()->create(['slug' => 'publica-a', 'title' => 'Pública A']);

        $this->churchB = Church::factory()->create();
        $this->classB = Classroom::factory()->create(['slug' => 'turma-b', 'church_id' => $this->churchB->id]);
        Lesson::factory()->for($this->classB)->published()->create(['slug' => 'publica-b', 'title' => 'Pública B']);
        Lesson::factory()->for($this->classB)->published()->membersOnly()->create(['slug' => 'membros-b', 'title' => 'Membros B']);
    }

    /**
     * @return list<string>
     */
    private function titles(TestResponse $response): array
    {
        return collect($response->viewData('page')['props']['results']['data'])->pluck('title')->all();
    }

    /**
     * @return list<string>
     */
    private function classroomSlugs(TestResponse $response): array
    {
        return collect($response->viewData('page')['props']['classrooms'])->pluck('slug')->all();
    }

    public function test_a_member_of_church_b_sees_only_church_b(): void
    {
        $student = User::factory()->studentOf($this->classB)->create();

        $library = $this->actingAs($student)->get('/biblioteca');
        $this->assertEqualsCanonicalizing(['Pública B', 'Membros B'], $this->titles($library));
        $this->assertSame(['turma-b'], $this->classroomSlugs($library));

        $this->assertSame(['turma-b'], $this->classroomSlugs($this->actingAs($student)->get('/')));
    }

    public function test_a_church_b_admin_sees_church_b_members_lessons(): void
    {
        $admin = User::factory()->create();
        $this->churchB->admins()->attach($admin);

        $this->assertEqualsCanonicalizing(['Pública B', 'Membros B'], $this->titles($this->actingAs($admin)->get('/biblioteca')));
    }

    public function test_the_system_owner_is_not_a_window_into_other_churches(): void
    {
        $owner = User::factory()->admin()->create();

        $this->assertSame(['Pública A'], $this->titles($this->actingAs($owner)->get('/biblioteca')));
    }

    public function test_a_visitor_sees_no_church_listing_and_public_links_still_open(): void
    {
        // Visitante: o site em "/" e, na biblioteca, só os estudos da biblioteca oficial.
        $this->assertSame([], $this->titles($this->get('/biblioteca')));
        $this->get('/')->assertInertia(fn (Assert $page) => $page->component('site/home'));

        $this->get('/licoes/publica-b')->assertOk();
        $this->get('/licoes/membros-b')->assertRedirect();
    }
}
