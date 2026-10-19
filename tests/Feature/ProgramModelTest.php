<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Classroom;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\ClassroomSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Programa de ensino: agrupa turmas de uma igreja (EBD, discipulado...).
 */
class ProgramModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_classrooms_of_a_church_share_its_default_program(): void
    {
        $a = Classroom::factory()->create();
        $b = Classroom::factory()->create();

        $this->assertSame($a->program_id, $b->program_id);
        $this->assertSame('EBD', $a->program->short_name);
        $this->assertSame($a->church_id, $a->program->church_id);
    }

    public function test_default_program_is_created_once(): void
    {
        $church = Church::factory()->create();

        $first = $church->defaultProgram();
        $second = $church->defaultProgram();

        $this->assertTrue($first->is($second));
        $this->assertSame(['Escola Bíblica Dominical', 'EBD'], [$first->name, $first->short_name]);
        $this->assertSame(1, $church->programs()->count());
    }

    public function test_coordinator_check(): void
    {
        $program = Program::factory()->create();
        $coordinator = User::factory()->create();
        $program->coordinators()->attach($coordinator);

        $this->assertTrue($coordinator->isCoordinatorOf($program));
        $this->assertFalse(User::factory()->create()->isCoordinatorOf($program));
    }

    public function test_seeder_puts_classrooms_in_the_ebd_program(): void
    {
        $this->seed(ClassroomSeeder::class);

        $programs = Classroom::query()->pluck('program_id')->unique();
        $this->assertCount(1, $programs);
        $this->assertSame('ebd', Program::query()->find($programs->first())->slug);
    }
}
