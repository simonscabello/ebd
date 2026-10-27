<?php

namespace Tests\Feature\Admin;

use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Painel da gestão: próximas lições (com encontro marcado) e as que ainda não
 * estão em nenhum encontro, cada lista com o seu limite.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 9, 27)->setTime(10, 0));
    }

    public function test_a_church_without_lessons_is_offered_to_create_one(): void
    {
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->teacherOf($classroom)->create();

        $this->actingAs($teacher)->get('/admin')
            ->assertInertia(fn (Assert $page) => $page
                ->has('scheduled', 0)
                ->where('hasLessons', false));

        // Lição só com encontro que já passou: existe, mas não está em nenhuma lista.
        $lesson = Lesson::factory()->for($classroom)->published()->create();
        ClassMeeting::factory()->forLesson($lesson)->on('2026-09-20')->held()->create();

        $this->actingAs($teacher)->get('/admin')
            ->assertInertia(fn (Assert $page) => $page
                ->has('scheduled', 0)
                ->where('hasLessons', true));
    }
}
