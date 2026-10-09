<?php

namespace Tests\Feature\Admin;

use App\Enums\Weekday;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonReadingsTest extends TestCase
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

    public function test_readings_are_appended_in_order_and_can_be_reordered(): void
    {
        foreach (['Isaías 6', 'Salmo 99', 'Lucas 5'] as $reference) {
            $this->actingAs($this->teacher)
                ->post("/admin/licoes/{$this->lesson->id}/leituras", ['reference' => $reference])
                ->assertSessionHasNoErrors();
        }

        $ids = $this->lesson->readings()->pluck('id')->all();
        $this->assertSame(['Isaías 6', 'Salmo 99', 'Lucas 5'], $this->lesson->readings()->pluck('reference')->all());

        $this->actingAs($this->teacher)
            ->put("/admin/licoes/{$this->lesson->id}/ordem/readings", ['ids' => [$ids[2], $ids[0], $ids[1]]])
            ->assertRedirect();

        $this->assertSame(['Lucas 5', 'Isaías 6', 'Salmo 99'], $this->lesson->readings()->pluck('reference')->all());
    }

    public function test_reorder_rejects_ids_from_other_lessons(): void
    {
        $mine = LessonReading::factory()->for($this->lesson)->create();
        $foreign = LessonReading::factory()->create();

        $this->actingAs($this->teacher)
            ->put("/admin/licoes/{$this->lesson->id}/ordem/readings", ['ids' => [$foreign->id, $mine->id]])
            ->assertSessionHasErrors('ids');
    }

    public function test_questions_are_no_longer_a_reorderable_relation(): void
    {
        $this->actingAs($this->teacher)
            ->put("/admin/licoes/{$this->lesson->id}/ordem/questions", ['ids' => []])
            ->assertNotFound();
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
