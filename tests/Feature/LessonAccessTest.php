<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Regra central de acesso: link do WhatsApp -> conteúdo, sem login, quando a
 * lição é pública; conteúdo restrito só para membros; rascunho só para professores.
 */
class LessonAccessTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::factory()->create(['slug' => 'jovens']);
    }

    public function test_guest_can_open_a_published_public_lesson_without_login(): void
    {
        $lesson = Lesson::factory()->published()->for($this->classroom)->create([
            'slug' => 'a-santidade-de-deus',
            'teacher_notes' => 'Segredo do professor',
        ]);

        $this->get('/licoes/a-santidade-de-deus')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('lessons/show')
                ->where('lesson.id', $lesson->id)
                ->where('lesson.title', $lesson->title)
                ->missing('lesson.teacher_notes_html')
                ->where('canManage', false));
    }

    public function test_guest_is_sent_to_login_for_members_only_lesson(): void
    {
        Lesson::factory()->published()->membersOnly()->for($this->classroom)->create(['slug' => 'restrita']);

        $this->get('/licoes/restrita')->assertRedirect(route('login'));
    }

    public function test_member_of_the_classroom_can_open_members_only_lesson(): void
    {
        $student = User::factory()->studentOf($this->classroom)->create();
        Lesson::factory()->published()->membersOnly()->for($this->classroom)->create(['slug' => 'restrita']);

        $this->actingAs($student)->get('/licoes/restrita')->assertOk();
    }

    public function test_logged_user_from_another_classroom_cannot_open_members_only_lesson(): void
    {
        $other = Classroom::factory()->create();
        $student = User::factory()->studentOf($other)->create();
        Lesson::factory()->published()->membersOnly()->for($this->classroom)->create(['slug' => 'restrita']);

        $this->actingAs($student)->get('/licoes/restrita')->assertNotFound();
    }

    public function test_drafts_are_hidden_from_guests_and_students(): void
    {
        $student = User::factory()->studentOf($this->classroom)->create();
        Lesson::factory()->for($this->classroom)->create(['slug' => 'rascunho']);

        $this->get('/licoes/rascunho')->assertNotFound();
        $this->actingAs($student)->get('/licoes/rascunho')->assertNotFound();
    }

    public function test_teacher_sees_draft_and_teacher_notes(): void
    {
        $teacher = User::factory()->teacherOf($this->classroom)->create();
        Lesson::factory()->for($this->classroom)->create([
            'slug' => 'rascunho',
            'teacher_notes' => '**Abrir com oração**',
        ]);

        $this->actingAs($teacher)->get('/licoes/rascunho/domingo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('lessons/sunday')
                ->where('lesson.teacher_notes_html', '<p><strong>Abrir com oração</strong></p>')
                ->where('canManage', true));
    }

    public function test_sunday_mode_is_available_to_readers_without_teacher_notes(): void
    {
        Lesson::factory()->published()->for($this->classroom)->create([
            'slug' => 'publica',
            'content' => "## Introdução\n\nTexto\n\n## Aplicação\n\nMais",
            'teacher_notes' => 'Notas',
        ]);

        $this->get('/licoes/publica/domingo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lesson.topics', ['Introdução', 'Aplicação'])
                ->missing('lesson.teacher_notes_html'));
    }

    public function test_lesson_content_is_rendered_without_raw_html(): void
    {
        Lesson::factory()->published()->for($this->classroom)->create([
            'slug' => 'xss',
            'content' => "Olá <script>alert(1)</script>\n\n[clique](javascript:alert(1))",
        ]);

        $this->get('/licoes/xss')->assertInertia(fn (Assert $page) => $page
            ->where('lesson.content_html', fn (string $html) => ! str_contains($html, '<script') && ! str_contains($html, 'javascript:')));
    }
}
