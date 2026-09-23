<?php

namespace Tests\Feature\Admin;

use App\Enums\LessonStatus;
use App\Events\LessonPublished;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LessonManagementTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::factory()->create(['slug' => 'adultos']);
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'classroom_id' => $this->classroom->id,
            'title' => 'A Santidade de Deus',
            'scheduled_for' => '2026-09-27',
            'bible_reference' => 'Lucas 5:1-11',
            'summary' => 'Resumo',
            'content' => "## Introdução\n\nTexto",
            'visibility' => 'public',
            ...$overrides,
        ];
    }

    public function test_teacher_creates_lesson_as_draft_with_author_and_slug(): void
    {
        $series = Series::factory()->for($this->classroom)->create();

        $response = $this->actingAs($this->teacher)->post('/admin/licoes', $this->payload(['series_id' => $series->id]));

        $lesson = Lesson::query()->sole();
        $response->assertRedirect(route('admin.lessons.edit', $lesson));
        $this->assertSame('a-santidade-de-deus', $lesson->slug);
        $this->assertSame(LessonStatus::Draft, $lesson->status);
        $this->assertSame($series->id, $lesson->series_id);
        $this->assertSame($this->teacher->id, $lesson->created_by);
        $this->assertTrue($lesson->authors()->whereKey($this->teacher->id)->exists());
    }

    public function test_slug_collision_uses_classroom_then_number(): void
    {
        Lesson::factory()->create(['slug' => 'a-santidade-de-deus']);

        $this->actingAs($this->teacher)->post('/admin/licoes', $this->payload());
        $this->actingAs($this->teacher)->post('/admin/licoes', $this->payload());

        $this->assertDatabaseHas('lessons', ['slug' => 'a-santidade-de-deus-adultos']);
        $this->assertDatabaseHas('lessons', ['slug' => 'a-santidade-de-deus-adultos-2']);
    }

    public function test_teacher_cannot_create_or_edit_lessons_of_another_classroom(): void
    {
        $other = Classroom::factory()->create();
        $foreign = Lesson::factory()->for($other)->create();

        $this->actingAs($this->teacher)->post('/admin/licoes', $this->payload(['classroom_id' => $other->id]))->assertForbidden();
        $this->actingAs($this->teacher)->get("/admin/licoes/{$foreign->id}/editar")->assertForbidden();
        $this->actingAs($this->teacher)->put("/admin/licoes/{$foreign->id}", $this->payload(['classroom_id' => null]))->assertForbidden();
        $this->actingAs($this->teacher)->post("/admin/licoes/{$foreign->id}/status", ['status' => 'published'])->assertForbidden();
    }

    public function test_series_must_belong_to_the_lesson_classroom(): void
    {
        $foreignSeries = Series::factory()->create();

        $this->actingAs($this->teacher)
            ->post('/admin/licoes', $this->payload(['series_id' => $foreignSeries->id]))
            ->assertSessionHasErrors('series_id');
    }

    public function test_status_fields_cannot_be_mass_assigned(): void
    {
        $this->actingAs($this->teacher)->post('/admin/licoes', $this->payload([
            'status' => 'published',
            'published_at' => now(),
            'created_by' => 999,
        ]));

        $lesson = Lesson::query()->sole();
        $this->assertSame(LessonStatus::Draft, $lesson->status);
        $this->assertNull($lesson->published_at);
        $this->assertSame($this->teacher->id, $lesson->created_by);
    }

    public function test_publish_complete_reopen_and_unpublish_lifecycle(): void
    {
        Event::fake([LessonPublished::class]);
        $lesson = Lesson::factory()->for($this->classroom)->create();

        $this->actingAs($this->teacher)->post("/admin/licoes/{$lesson->id}/status", ['status' => 'published'])->assertRedirect();
        $this->assertSame(LessonStatus::Published, $lesson->refresh()->status);
        $this->assertNotNull($lesson->published_at);
        Event::assertDispatched(LessonPublished::class);

        $this->actingAs($this->teacher)->post("/admin/licoes/{$lesson->id}/status", ['status' => 'completed']);
        $this->assertSame(LessonStatus::Completed, $lesson->refresh()->status);
        $this->assertNotNull($lesson->completed_at);

        // Concluída não volta direto para rascunho.
        $this->actingAs($this->teacher)->post("/admin/licoes/{$lesson->id}/status", ['status' => 'draft'])->assertSessionHasErrors('status');

        $this->actingAs($this->teacher)->post("/admin/licoes/{$lesson->id}/status", ['status' => 'published']);
        $this->assertSame(LessonStatus::Published, $lesson->refresh()->status);
        $this->assertNull($lesson->completed_at);

        $this->actingAs($this->teacher)->post("/admin/licoes/{$lesson->id}/status", ['status' => 'draft']);
        $this->assertSame(LessonStatus::Draft, $lesson->refresh()->status);
    }

    public function test_lesson_without_date_cannot_be_published(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->create(['scheduled_for' => null]);

        $this->actingAs($this->teacher)
            ->post("/admin/licoes/{$lesson->id}/status", ['status' => 'published'])
            ->assertSessionHasErrors('scheduled_for');
    }

    public function test_slug_is_locked_after_publication(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->create(['slug' => 'original']);

        $this->actingAs($this->teacher)->put("/admin/licoes/{$lesson->id}", $this->payload([
            'classroom_id' => null, 'slug' => 'novo-endereco',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('novo-endereco', $lesson->refresh()->slug);

        $lesson->forceFill(['status' => LessonStatus::Published])->save();

        $this->actingAs($this->teacher)->put("/admin/licoes/{$lesson->id}", $this->payload([
            'classroom_id' => null, 'slug' => 'outro', 'title' => 'Título alterado',
        ]));
        $lesson->refresh();
        $this->assertSame('novo-endereco', $lesson->slug);
        $this->assertSame('Título alterado', $lesson->title);
    }

    public function test_deleted_lessons_keep_their_slug_reserved(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->create(['slug' => 'a-santidade-de-deus']);

        $this->actingAs($this->teacher)->delete("/admin/licoes/{$lesson->id}")->assertRedirect();
        $this->assertSoftDeleted($lesson);

        $this->actingAs($this->teacher)->post('/admin/licoes', $this->payload());
        $this->assertDatabaseHas('lessons', ['slug' => 'a-santidade-de-deus-adultos']);
        $this->get('/licoes/a-santidade-de-deus')->assertNotFound();
    }
}
