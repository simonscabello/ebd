<?php

namespace Tests\Feature\Admin;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\StudyQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Aba "Dúvidas" da classe: o que a turma perguntou à IA, por lição, sem nomes.
 */
class ClassroomQuestionsTest extends TestCase
{
    use RefreshDatabase;

    private function ask(User $user, Lesson $lesson, string $question): void
    {
        StudyQuestion::query()->create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'question' => $question,
            'answer' => "Resposta para: {$question}",
        ]);
    }

    public function test_teacher_sees_the_class_questions_by_lesson_without_names(): void
    {
        $classroom = Classroom::factory()->create(['slug' => 'jovens']);
        $teacher = User::factory()->teacherOf($classroom)->create();
        $ana = User::factory()->studentOf($classroom)->create(['name' => 'Ana Souza']);
        $bia = User::factory()->studentOf($classroom)->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $older = Lesson::factory()->for($classroom)->published()->create(['slug' => 'antiga', 'title' => 'Antiga']);
        $newer = Lesson::factory()->for($classroom)->published()->number(2)->create(['slug' => 'nova', 'title' => 'Nova']);
        $otherClass = Lesson::factory()->for(Classroom::factory())->published()->create();

        $this->ask($ana, $older, 'Quem era Nicodemos?');
        $this->travel(1)->hours();
        $this->ask($ana, $newer, 'O que é Siloé?');
        $this->ask($bia, $newer, 'Por que no sábado?');
        $this->ask($teacher, $newer, 'Pergunta do professor');
        $this->ask($admin, $newer, 'Pergunta do admin');
        $this->ask($bia, $otherClass, 'De outra classe');
        $this->travel(1)->minutes();
        $this->ask($ana, $newer, 'Por que no sábado? ');

        $this->actingAs($teacher)
            ->get('/admin/classes/jovens/duvidas')
            ->assertOk()
            ->assertDontSee('Ana Souza')
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/classrooms/questions')
                ->has('lessons', 2)
                ->where('lessons.0.title', 'Lição 2 — Nova')
                ->where('lessons.0.total', 3)
                ->where('lessons.0.people', 2)
                ->has('lessons.0.questions', 2)
                ->where('lessons.0.questions.0.question', 'Por que no sábado? ')
                ->where('lessons.0.questions.0.times', 2)
                ->where('lessons.0.questions.1.times', 1)
                ->where('lessons.0.questions.1.answer', 'Resposta para: O que é Siloé?')
                ->missing('lessons.0.questions.0.user_id')
                ->where('lessons.1.title', 'Antiga')
                ->where('lessons.1.total', 1));
    }

    public function test_only_who_manages_the_class_can_see(): void
    {
        $classroom = Classroom::factory()->create(['slug' => 'jovens']);
        $student = User::factory()->studentOf($classroom)->create();
        $otherTeacher = User::factory()->teacherOf(Classroom::factory()->create())->create();

        $this->actingAs($student)->get('/admin/classes/jovens/duvidas')->assertForbidden();
        $this->actingAs($otherTeacher)->get('/admin/classes/jovens/duvidas')->assertForbidden();
    }
}
