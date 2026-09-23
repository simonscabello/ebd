<?php

namespace Tests\Feature\Admin;

use App\Enums\Weekday;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonQuestionsAndReadingsTest extends TestCase
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

    public function test_questions_are_appended_in_order_and_can_be_reordered(): void
    {
        foreach (['Primeira?', 'Segunda?', 'Terceira?'] as $body) {
            $this->actingAs($this->teacher)
                ->post("/admin/licoes/{$this->lesson->id}/perguntas", ['body' => $body])
                ->assertSessionHasNoErrors();
        }

        $ids = $this->lesson->questions()->pluck('id')->all();
        $this->assertSame(['Primeira?', 'Segunda?', 'Terceira?'], $this->lesson->questions()->pluck('body')->all());

        $this->actingAs($this->teacher)
            ->put("/admin/licoes/{$this->lesson->id}/ordem/questions", ['ids' => [$ids[2], $ids[0], $ids[1]]])
            ->assertRedirect();

        $this->assertSame(['Terceira?', 'Primeira?', 'Segunda?'], $this->lesson->questions()->pluck('body')->all());
    }

    public function test_reorder_rejects_ids_from_other_lessons(): void
    {
        $mine = LessonQuestion::factory()->for($this->lesson)->create();
        $foreign = LessonQuestion::factory()->create();

        $this->actingAs($this->teacher)
            ->put("/admin/licoes/{$this->lesson->id}/ordem/questions", ['ids' => [$foreign->id, $mine->id]])
            ->assertSessionHasErrors('ids');
    }

    public function test_questions_can_be_edited_and_removed(): void
    {
        $question = LessonQuestion::factory()->for($this->lesson)->create();

        $this->actingAs($this->teacher)
            ->put("/admin/licoes/{$this->lesson->id}/perguntas/{$question->id}", ['body' => 'Nova redação?'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Nova redação?', $question->refresh()->body);

        $this->actingAs($this->teacher)->delete("/admin/licoes/{$this->lesson->id}/perguntas/{$question->id}");
        $this->assertModelMissing($question);
    }

    public function test_empty_question_is_rejected(): void
    {
        $this->actingAs($this->teacher)
            ->post("/admin/licoes/{$this->lesson->id}/perguntas", ['body' => ''])
            ->assertSessionHasErrors('body');
    }

    public function test_readings_with_optional_weekday(): void
    {
        $this->actingAs($this->teacher)->post("/admin/licoes/{$this->lesson->id}/leituras", [
            'weekday' => 1, 'reference' => 'Isaías 6:1-8', 'notes' => 'A visão de Isaías',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->teacher)->post("/admin/licoes/{$this->lesson->id}/leituras", [
            'weekday' => null, 'reference' => 'Salmo 99',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->teacher)->post("/admin/licoes/{$this->lesson->id}/leituras", [
            'weekday' => 9, 'reference' => 'Inválido',
        ])->assertSessionHasErrors('weekday');

        $readings = $this->lesson->readings()->get();
        $this->assertCount(2, $readings);
        $this->assertSame(Weekday::Monday, $readings[0]->weekday);
        $this->assertNull($readings[1]->weekday);
    }
}
