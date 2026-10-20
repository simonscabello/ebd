<?php

namespace Tests\Feature\Admin;

use App\Models\Church;
use App\Models\Classroom;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Tela de programas: a administração da igreja cria, renomeia, nomeia
 * coordenadores e escolhe o programa de cada classe. Sem passar de igreja.
 */
class ProgramManagementTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $jovens;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jovens = Classroom::factory()->create(['slug' => 'jovens']);
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_sees_programs_with_classrooms_and_coordinators(): void
    {
        $coordinator = User::factory()->create(['name' => 'Rita']);
        $this->jovens->program->coordinators()->attach($coordinator);

        $this->actingAs($this->admin)->get('/admin/programas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/programs/index')
                ->has('programs', 1)
                ->where('programs.0.short_name', 'EBD')
                ->where('programs.0.classrooms.0.slug', 'jovens')
                ->where('programs.0.coordinators.0.name', 'Rita'));
    }

    public function test_admin_creates_and_renames_a_program(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/programas', ['name' => 'Discipulado', 'short_name' => 'Disc.'])
            ->assertSessionHasNoErrors();

        $program = Program::query()->where('name', 'Discipulado')->firstOrFail();
        $this->assertSame($this->jovens->church_id, $program->church_id);
        $this->assertSame('discipulado', $program->slug);

        // Nome repetido na mesma igreja não.
        $this->actingAs($this->admin)
            ->post('/admin/programas', ['name' => 'Discipulado', 'short_name' => 'D'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)
            ->put("/admin/programas/{$program->id}", ['name' => 'Discipulado de novos', 'short_name' => 'Discipulado'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Discipulado', $program->refresh()->short_name);
    }

    public function test_teachers_and_coordinators_do_not_manage_programs(): void
    {
        $teacher = User::factory()->teacherOf($this->jovens)->create();
        $coordinator = User::factory()->create();
        $this->jovens->program->coordinators()->attach($coordinator);

        foreach ([$teacher, $coordinator] as $user) {
            $this->actingAs($user)->get('/admin/programas')->assertForbidden();
            $this->actingAs($user)->post('/admin/programas', ['name' => 'X', 'short_name' => 'X'])->assertForbidden();
            $this->actingAs($user)->put("/admin/programas/{$this->jovens->program_id}", ['name' => 'X', 'short_name' => 'X'])->assertForbidden();
        }

        $this->assertSame(1, Program::query()->count());
    }

    public function test_admin_appoints_and_removes_a_coordinator_from_the_screen(): void
    {
        $candidate = User::factory()->create(['email' => 'rita@example.com']);
        $program = $this->jovens->program;

        $this->actingAs($this->admin)->from('/admin/programas')
            ->post("/admin/programas/{$program->id}/coordenadores", ['email' => 'rita@example.com'])
            ->assertRedirect('/admin/programas');
        $this->assertTrue($candidate->isCoordinatorOf($program));

        $this->actingAs($this->admin)->from('/admin/programas')
            ->delete("/admin/programas/{$program->id}/coordenadores/{$candidate->id}")
            ->assertRedirect('/admin/programas');
        $this->assertFalse($candidate->fresh()->isCoordinatorOf($program));
    }

    public function test_another_church_cannot_touch_these_programs(): void
    {
        $churchB = Church::factory()->create();
        $adminB = User::factory()->create();
        $churchB->admins()->attach($adminB);
        $programB = Program::factory()->create(['church_id' => $churchB->id]);
        $memberHere = User::factory()->studentOf($this->jovens)->create(['email' => 'ana@example.com']);
        $program = $this->jovens->program;

        $this->actingAs($adminB)->put("/admin/programas/{$program->id}", ['name' => 'X', 'short_name' => 'X'])->assertForbidden();
        $this->actingAs($adminB)->post("/admin/programas/{$program->id}/coordenadores", ['email' => 'ana@example.com'])->assertForbidden();

        // Nem levar alguém desta igreja para coordenar lá.
        $this->actingAs($adminB)
            ->post("/admin/programas/{$programB->id}/coordenadores", ['email' => 'ana@example.com'])
            ->assertSessionHasErrors('email');
        $this->assertFalse($memberHere->isCoordinatorOf($programB));

        // A lista de B mostra só os programas de B.
        $this->actingAs($adminB)->get('/admin/programas')
            ->assertInertia(fn (Assert $page) => $page->has('programs', 1)->where('programs.0.id', $programB->id));

        // Criar sempre na própria igreja.
        $this->actingAs($adminB)->post('/admin/programas', ['name' => 'Células', 'short_name' => 'Células']);
        $this->assertSame($churchB->id, Program::query()->where('name', 'Células')->value('church_id'));
    }

    public function test_classroom_is_created_in_the_chosen_program_and_can_move(): void
    {
        $discipulado = Program::factory()->create(['church_id' => $this->jovens->church_id, 'name' => 'Discipulado']);

        $this->actingAs($this->admin)->get('/admin/classes/criar')
            ->assertInertia(fn (Assert $page) => $page
                ->has('programs', 2)
                ->where('defaultProgramId', $this->jovens->program_id));

        $this->actingAs($this->admin)
            ->post('/admin/classes', ['name' => 'Novos convertidos', 'program_id' => $discipulado->id, 'meeting_weekday' => 3])
            ->assertSessionHasNoErrors();
        $created = Classroom::query()->where('name', 'Novos convertidos')->firstOrFail();
        $this->assertSame($discipulado->id, $created->program_id);

        // Sem escolher, vai para o programa padrão.
        $this->actingAs($this->admin)->post('/admin/classes', ['name' => 'Adultos'])->assertSessionHasNoErrors();
        $this->assertSame($this->jovens->program_id, Classroom::query()->where('name', 'Adultos')->value('program_id'));

        $this->actingAs($this->admin)
            ->put('/admin/classes/jovens', ['name' => 'Jovens', 'program_id' => $discipulado->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($discipulado->id, $this->jovens->refresh()->program_id);
    }

    public function test_classroom_cannot_go_to_a_program_of_another_church(): void
    {
        $programB = Program::factory()->create(['church_id' => Church::factory()->create()->id]);

        $this->actingAs($this->admin)
            ->post('/admin/classes', ['name' => 'Intrusa', 'program_id' => $programB->id])
            ->assertSessionHasErrors('program_id');
        $this->actingAs($this->admin)
            ->put('/admin/classes/jovens', ['name' => 'Jovens', 'program_id' => $programB->id])
            ->assertSessionHasErrors('program_id');

        $this->assertNotSame($programB->id, $this->jovens->refresh()->program_id);
        $this->assertFalse(Classroom::query()->where('name', 'Intrusa')->exists());
    }
}
