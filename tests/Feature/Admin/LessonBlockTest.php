<?php

namespace Tests\Feature\Admin;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonBlockTest extends TestCase
{
    use RefreshDatabase;

    private Lesson $lesson;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $classroom = Classroom::factory()->create();
        $this->teacher = User::factory()->teacherOf($classroom)->create();
        $this->lesson = Lesson::factory()->for($classroom)->create();
    }

    public function test_audience_defaults_to_the_kind_and_blocks_can_be_reordered(): void
    {
        $this->actingAs($this->teacher)->post("/admin/licoes/{$this->lesson->id}/blocos", [
            'kind' => 'roteiro', 'body' => 'Abrir com a pergunta do barco',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->teacher)->post("/admin/licoes/{$this->lesson->id}/blocos", [
            'kind' => 'curiosity', 'title' => 'Siloé', 'body' => 'Encontrada em 2004', 'drip_weekday' => 2,
        ])->assertSessionHasNoErrors();

        [$roteiro, $curiosidade] = $this->lesson->blocks()->get()->all();
        $this->assertSame('teacher', $roteiro->audience->value);
        $this->assertSame('student', $curiosidade->audience->value);
        $this->assertSame(2, $curiosidade->drip_weekday?->value);

        $this->actingAs($this->teacher)
            ->put("/admin/licoes/{$this->lesson->id}/ordem/blocks", ['ids' => [$curiosidade->id, $roteiro->id]])
            ->assertSessionHasNoErrors();
        $this->assertSame([$curiosidade->id, $roteiro->id], $this->lesson->blocks()->pluck('id')->all());
    }

    public function test_teacher_block_cannot_be_dripped_to_students(): void
    {
        $this->actingAs($this->teacher)->post("/admin/licoes/{$this->lesson->id}/blocos", [
            'kind' => 'context', 'audience' => 'teacher', 'body' => 'x', 'drip_weekday' => 3,
        ])->assertSessionHasErrors('drip_weekday');
    }

    public function test_search_text_only_contains_student_blocks(): void
    {
        $this->actingAs($this->teacher)->post("/admin/licoes/{$this->lesson->id}/blocos", [
            'kind' => 'concept', 'title' => 'As 39 melachot', 'body' => 'Categorias de trabalho proibido no sábado.',
        ]);
        $this->actingAs($this->teacher)->post("/admin/licoes/{$this->lesson->id}/blocos", [
            'kind' => 'accuracy_note', 'body' => 'Globos oculares é especulação homilética.',
        ]);

        $text = (string) $this->lesson->refresh()->blocks_text;
        $this->assertStringContainsString('melachot', $text);
        $this->assertStringNotContainsString('homilética', $text);

        $block = $this->lesson->blocks()->where('kind', 'concept')->sole();
        $this->actingAs($this->teacher)->delete("/admin/licoes/{$this->lesson->id}/blocos/{$block->id}");
        $this->assertNull($this->lesson->refresh()->blocks_text);
    }

    public function test_blocks_of_another_lesson_cannot_be_edited_through_this_lesson(): void
    {
        $foreign = LessonBlock::factory()->create();

        $this->actingAs($this->teacher)
            ->put("/admin/licoes/{$this->lesson->id}/blocos/{$foreign->id}", ['kind' => 'curiosity', 'body' => 'x'])
            ->assertNotFound();
    }

    public function test_review_question_requires_an_answer_key(): void
    {
        $this->actingAs($this->teacher)
            ->post("/admin/licoes/{$this->lesson->id}/perguntas", ['kind' => 'review', 'body' => 'O que significa Siloé?'])
            ->assertSessionHasErrors('answer');

        $this->actingAs($this->teacher)
            ->post("/admin/licoes/{$this->lesson->id}/perguntas", ['kind' => 'review', 'body' => 'O que significa Siloé?', 'answer' => 'Enviado'])
            ->assertSessionHasNoErrors();

        // Reflexão não guarda gabarito.
        $this->actingAs($this->teacher)
            ->post("/admin/licoes/{$this->lesson->id}/perguntas", ['kind' => 'reflection', 'body' => 'Para onde você corre?', 'answer' => 'ignorado'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Enviado', null], $this->lesson->questions()->pluck('answer')->all());
    }
}
