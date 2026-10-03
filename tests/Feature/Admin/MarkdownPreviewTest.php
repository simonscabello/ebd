<?php

namespace Tests\Feature\Admin;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prévia do Markdown no editor de lição.
 */
class MarkdownPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_gets_the_same_html_the_student_will_see(): void
    {
        $teacher = User::factory()->teacherOf(Classroom::factory()->create())->create();

        $this->actingAs($teacher)
            ->postJson('/admin/markdown/previa', ['text' => "## I. Introdução\n\n**Deus** é santo.<script>alert(1)</script>"])
            ->assertOk()
            ->assertJsonPath('html', fn (string $html) => str_contains($html, '<h2>I. Introdução</h2>')
                && str_contains($html, '<strong>Deus</strong>')
                && ! str_contains($html, '<script>'));
    }

    public function test_empty_text_has_no_preview(): void
    {
        $teacher = User::factory()->teacherOf(Classroom::factory()->create())->create();

        $this->actingAs($teacher)
            ->postJson('/admin/markdown/previa', ['text' => ''])
            ->assertOk()
            ->assertJsonPath('html', null);
    }

    public function test_students_cannot_use_the_preview(): void
    {
        $student = User::factory()->studentOf(Classroom::factory()->create())->create();

        $this->actingAs($student)
            ->postJson('/admin/markdown/previa', ['text' => 'oi'])
            ->assertForbidden();
    }
}
