<?php

namespace Tests\Feature;

use App\Enums\ContentAudience;
use App\Enums\LessonBlockKind;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\LessonMaterial;
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
        ]);
        LessonBlock::factory()->for($lesson)->teacherOnly()->create(['body' => 'Segredo do professor']);

        $this->get('/licoes/a-santidade-de-deus')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertInertia(fn (Assert $page) => $page
                ->component('lessons/show')
                ->where('lesson.id', $lesson->id)
                ->where('lesson.title', $lesson->title)
                ->missing('lesson.teacher_blocks')
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

    public function test_teacher_sees_draft_and_teacher_content(): void
    {
        $teacher = User::factory()->teacherOf($this->classroom)->create();
        $lesson = Lesson::factory()->for($this->classroom)->create(['slug' => 'rascunho']);
        LessonBlock::factory()->for($lesson)->teacherOnly()->create(['body' => '**Abrir com oração**']);

        $this->actingAs($teacher)->get('/licoes/rascunho/domingo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('lessons/sunday')
                ->where('lesson.teacher_blocks.0.body_html', '<p><strong>Abrir com oração</strong></p>')
                ->where('lesson.teacher_blocks.0.kind', 'roteiro')
                ->where('canManage', true));
    }

    public function test_sunday_mode_is_available_to_readers_without_teacher_content(): void
    {
        $lesson = Lesson::factory()->published()->for($this->classroom)->create([
            'slug' => 'publica',
            'content' => "## Introdução\n\nTexto\n\n## Aplicação\n\nMais",
        ]);
        LessonBlock::factory()->for($lesson)->teacherOnly()->create();

        $this->get('/licoes/publica/domingo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lesson.topics', ['Introdução', 'Aplicação'])
                ->missing('lesson.teacher_blocks'));
    }

    public function test_teacher_only_blocks_and_materials_never_reach_students(): void
    {
        $student = User::factory()->studentOf($this->classroom)->create();
        $teacher = User::factory()->teacherOf($this->classroom)->create();
        $lesson = Lesson::factory()->published()->for($this->classroom)->create(['slug' => 'licao']);
        LessonBlock::factory()->for($lesson)->kind(LessonBlockKind::Curiosity)->create(['body' => 'Siloé foi encontrada em 2004']);
        LessonBlock::factory()->for($lesson)->kind(LessonBlockKind::AccuracyNote)->create(['body' => 'Especulação homilética']);
        LessonMaterial::factory()->for($lesson)->create(['title' => 'Guia do aluno']);
        LessonMaterial::factory()->for($lesson)->create(['title' => 'Manual completo', 'audience' => ContentAudience::Teacher]);

        foreach ([null, $student] as $reader) {
            ($reader ? $this->actingAs($reader) : $this)->get('/licoes/licao')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->has('lesson.blocks', 1)
                    ->where('lesson.blocks.0.kind', 'curiosity')
                    ->has('lesson.materials', 1)
                    ->where('lesson.materials.0.title', 'Guia do aluno')
                    ->missing('lesson.teacher_blocks'));
        }

        $this->actingAs($teacher)->get('/licoes/licao')
            ->assertInertia(fn (Assert $page) => $page
                ->has('lesson.blocks', 1)
                ->has('lesson.teacher_blocks', 1)
                ->has('lesson.materials', 2));
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
