<?php

namespace Tests\Feature\Isolation;

use App\Mcp\Servers\EbdServer;
use App\Mcp\Tools\GetLesson;
use App\Mcp\Tools\ListClassrooms;
use App\Models\Church;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Duas igrejas no mesmo app: quem administra, coordena ou ensina na igreja B
 * não vê nem altera nada da igreja A.
 */
class ChurchIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classA;

    private Lesson $lessonA;

    private ClassMeeting $meetingA;

    private Series $seriesA;

    private Church $churchB;

    private Classroom $classB;

    private User $adminB;

    private User $teacherB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classA = Classroom::factory()->create(['slug' => 'jovens-a']);
        User::factory()->teacherOf($this->classA)->create();
        User::factory()->studentOf($this->classA)->create();
        $this->seriesA = Series::factory()->for($this->classA)->create();
        $this->lessonA = Lesson::factory()->for($this->classA)->create(['slug' => 'rascunho-a']);
        $this->meetingA = ClassMeeting::factory()->for($this->classA)->on(now()->next('Sunday')->toDateString())->create();

        $this->churchB = Church::factory()->create(['name' => 'Igreja B']);
        $this->classB = Classroom::factory()->create(['slug' => 'jovens-b', 'church_id' => $this->churchB->id]);
        $this->adminB = User::factory()->create();
        $this->churchB->admins()->attach($this->adminB);
        $this->teacherB = User::factory()->teacherOf($this->classB)->create();
    }

    /**
     * @return list<User>
     */
    private function outsiders(): array
    {
        return [$this->adminB, $this->teacherB];
    }

    public function test_church_b_cannot_open_church_a_pages(): void
    {
        foreach ($this->outsiders() as $user) {
            foreach (['', '/domingos', '/alunos', '/relatorio', '/duvidas', '/editar'] as $page) {
                $this->actingAs($user)->get("/admin/classes/jovens-a{$page}")->assertForbidden();
            }
            $this->actingAs($user)->get("/admin/licoes/{$this->lessonA->id}/editar")->assertForbidden();
            $this->actingAs($user)->get('/licoes/rascunho-a')->assertNotFound();
        }
    }

    public function test_church_b_cannot_change_church_a(): void
    {
        foreach ($this->outsiders() as $user) {
            $this->actingAs($user)->put('/admin/classes/jovens-a', ['name' => 'Invadida', 'is_active' => true])->assertForbidden();
            $this->actingAs($user)->put("/admin/encontros/{$this->meetingA->id}", ['held_on' => $this->meetingA->held_on->toDateString(), 'title' => 'x'])->assertForbidden();
            $this->actingAs($user)->post("/admin/encontros/{$this->meetingA->id}/cancelar")->assertForbidden();
            $this->actingAs($user)->put("/admin/licoes/{$this->lessonA->id}", ['title' => 'Invadida'])->assertForbidden();
            $this->actingAs($user)->delete("/admin/series/{$this->seriesA->id}")->assertForbidden();
        }

        $this->assertNotSame('Invadida', $this->classA->refresh()->name);
        $this->assertNotSame('Invadida', $this->lessonA->refresh()->title);
        $this->assertFalse($this->meetingA->refresh()->isCancelled());
        $this->assertNotNull($this->seriesA->fresh());
    }

    public function test_church_b_lists_and_dashboard_show_only_church_b(): void
    {
        $this->actingAs($this->adminB)->get('/admin/classes')
            ->assertInertia(fn (Assert $page) => $page->where('classrooms', fn ($items) => collect($items)->pluck('slug')->all() === ['jovens-b']));

        $this->actingAs($this->adminB)->get('/admin')->assertOk()->assertDontSee('jovens-a');
        $this->actingAs($this->adminB)->get('/admin/licoes')->assertOk()->assertDontSee('rascunho-a');
    }

    public function test_church_b_admin_manages_church_b_and_creates_classrooms_there(): void
    {
        $this->actingAs($this->adminB)->get('/admin/classes/jovens-b')->assertOk();
        $this->actingAs($this->adminB)->put('/admin/classes/jovens-b', ['name' => 'Jovens B', 'is_active' => true])->assertRedirect();

        $this->actingAs($this->adminB)->post('/admin/classes', ['name' => 'Casais B', 'is_active' => true])->assertRedirect();
        $this->assertSame($this->churchB->id, Classroom::query()->where('name', 'Casais B')->value('church_id'));

        $this->actingAs($this->teacherB)->post('/admin/classes', ['name' => 'Não pode', 'is_active' => true])->assertForbidden();
    }

    public function test_mcp_only_sees_church_b(): void
    {
        EbdServer::actingAs($this->adminB)->tool(ListClassrooms::class, [])
            ->assertOk()->assertSee('jovens-b')->assertDontSee('jovens-a');

        EbdServer::actingAs($this->adminB)->tool(GetLesson::class, ['lesson' => (string) $this->lessonA->id])
            ->assertHasErrors();
    }

    public function test_church_name_shown_is_the_users_church(): void
    {
        $this->actingAs($this->teacherB)->get('/admin/classes/jovens-b')
            ->assertInertia(fn (Assert $page) => $page->where('church.name', 'Igreja B'));
    }
}
