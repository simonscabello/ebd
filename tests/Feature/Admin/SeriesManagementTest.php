<?php

namespace Tests\Feature\Admin;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeriesManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_creates_series_in_own_classroom(): void
    {
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->teacherOf($classroom)->create();

        $this->actingAs($teacher)->post('/admin/series', [
            'classroom_id' => $classroom->id,
            'title' => 'Evangelho de João',
            'description' => 'Estudo do quarto evangelho',
            'starts_on' => '2026-10-04',
            'ends_on' => '2026-12-20',
        ])->assertRedirect(route('admin.series.index'));

        $this->assertDatabaseHas('series', [
            'classroom_id' => $classroom->id,
            'title' => 'Evangelho de João',
            'slug' => 'evangelho-de-joao',
        ]);
    }

    public function test_teacher_cannot_create_series_in_another_classroom(): void
    {
        $teacher = User::factory()->teacherOf(Classroom::factory()->create())->create();
        $other = Classroom::factory()->create();

        $this->actingAs($teacher)->post('/admin/series', [
            'classroom_id' => $other->id,
            'title' => 'Invasão',
        ])->assertForbidden();

        $this->assertDatabaseMissing('series', ['title' => 'Invasão']);
    }

    public function test_series_validation(): void
    {
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->teacherOf($classroom)->create();

        $this->actingAs($teacher)->post('/admin/series', [
            'classroom_id' => $classroom->id,
            'title' => '',
            'starts_on' => '2026-10-04',
            'ends_on' => '2026-01-01',
        ])->assertSessionHasErrors(['title', 'ends_on']);
    }

    public function test_series_with_lessons_cannot_be_deleted(): void
    {
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->teacherOf($classroom)->create();
        $series = Series::factory()->for($classroom)->create();
        Lesson::factory()->forSeries($series)->create();

        $this->actingAs($teacher)->delete("/admin/series/{$series->id}")->assertSessionHasErrors('series');
        $this->assertModelExists($series);

        $empty = Series::factory()->for($classroom)->create();
        $this->actingAs($teacher)->delete("/admin/series/{$empty->id}")->assertRedirect();
        $this->assertModelMissing($empty);
    }
}
