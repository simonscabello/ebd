<?php

namespace Tests\Feature\Admin;

use App\Models\Classroom;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coordenador de programa: gerencia todas as turmas do programa, e só elas.
 */
class ProgramCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_manages_every_classroom_of_the_program_only(): void
    {
        $jovens = Classroom::factory()->create(['slug' => 'jovens']);
        $adultos = Classroom::factory()->create(['slug' => 'adultos']);
        $discipulado = Program::factory()->create(['church_id' => $jovens->church_id]);
        $grupo = Classroom::factory()->create(['slug' => 'grupo', 'program_id' => $discipulado->id]);

        $coordinator = User::factory()->create();
        $jovens->program->coordinators()->attach($coordinator);

        foreach (['jovens', 'adultos'] as $slug) {
            $this->actingAs($coordinator)->get("/admin/classes/{$slug}")->assertOk();
            $this->actingAs($coordinator)->get("/admin/classes/{$slug}/domingos")->assertOk();
            $this->actingAs($coordinator)->get("/admin/classes/{$slug}/relatorio")->assertOk();
        }

        $this->actingAs($coordinator)->get('/admin/classes/grupo')->assertForbidden();
        $this->assertTrue($coordinator->canAccessAdmin());
        $this->assertEqualsCanonicalizing([$jovens->id, $adultos->id], $coordinator->manageableClassroomIds() ?? []);
        $this->assertFalse($coordinator->canManageClassroom($grupo));
    }

    public function test_only_admins_appoint_coordinators(): void
    {
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->teacherOf($classroom)->create();
        $candidate = User::factory()->create(['email' => 'coord@example.com']);
        $program = $classroom->program;

        $this->actingAs($teacher)->post("/admin/programas/{$program->id}/coordenadores", ['email' => 'coord@example.com'])->assertForbidden();
        $this->assertFalse($candidate->isCoordinatorOf($program));

        $this->actingAs(User::factory()->admin()->create())
            ->post("/admin/programas/{$program->id}/coordenadores", ['email' => 'Coord@Example.com'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($candidate->isCoordinatorOf($program));

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/admin/programas/{$program->id}/coordenadores/{$candidate->id}")
            ->assertSessionHasNoErrors();
        $this->assertFalse($candidate->fresh()->isCoordinatorOf($program));
    }

    public function test_appointing_a_coordinator_revokes_personal_links(): void
    {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->studentOf($classroom)->create(['email' => 'aluna@example.com']);
        $link = $student->accessLinks()->forceCreate(['classroom_id' => $classroom->id, 'token_hash' => str_repeat('a', 64)]);

        $this->actingAs(User::factory()->admin()->create())
            ->post("/admin/programas/{$classroom->program_id}/coordenadores", ['email' => 'aluna@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($link->refresh()->revoked_at);
    }
}
