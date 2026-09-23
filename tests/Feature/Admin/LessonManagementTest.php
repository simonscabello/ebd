<?php

namespace Tests\Feature\Admin;

use App\Enums\LessonStatus;
use App\Events\LessonPublished;
use App\Models\ClassMeeting;
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

    public function test_publish_and_unpublish_lifecycle(): void
    {
        Event::fake([LessonPublished::class]);
        $lesson = Lesson::factory()->for($this->classroom)->create();

        $this->actingAs($this->teacher)->post("/admin/licoes/{$lesson->id}/status", ['status' => 'published'])->assertRedirect();
        $this->assertSame(LessonStatus::Published, $lesson->refresh()->status);
        $this->assertNotNull($lesson->published_at);
        Event::assertDispatched(LessonPublished::class);

        // "Concluída" deixou de ser status: vem dos encontros.
        $this->actingAs($this->teacher)->post("/admin/licoes/{$lesson->id}/status", ['status' => 'completed'])->assertSessionHasErrors('status');

        $this->actingAs($this->teacher)->post("/admin/licoes/{$lesson->id}/status", ['status' => 'draft']);
        $this->assertSame(LessonStatus::Draft, $lesson->refresh()->status);
        $this->assertNull($lesson->published_at);
    }

    public function test_lesson_can_be_published_before_having_a_date(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->create();

        $this->actingAs($this->teacher)
            ->post("/admin/licoes/{$lesson->id}/status", ['status' => 'published'])
            ->assertSessionHasNoErrors();

        $this->assertSame(LessonStatus::Published, $lesson->refresh()->status);
        $this->assertNull($lesson->scheduled_for);
    }

    public function test_creating_with_a_sunday_schedules_the_lesson_in_the_agenda(): void
    {
        $this->actingAs($this->teacher)->post('/admin/licoes', $this->payload(['meeting_on' => '2026-09-27']))->assertSessionHasNoErrors();

        $lesson = Lesson::query()->sole();
        $this->assertSame('2026-09-27', $lesson->scheduled_for?->toDateString());
        $this->assertDatabaseHas('class_meetings', [
            'classroom_id' => $this->classroom->id,
            'lesson_id' => $lesson->id,
            'held_on' => '2026-09-27',
            'status' => 'planned',
        ]);

        // O mesmo domingo já tem lição: a nova precisa ser ajustada pela agenda.
        $this->actingAs($this->teacher)
            ->post('/admin/licoes', $this->payload(['title' => 'Outra', 'meeting_on' => '2026-09-27']))
            ->assertSessionHasErrors('meeting_on');
    }

    public function test_revista_number_is_unique_within_the_series(): void
    {
        $series = Series::factory()->for($this->classroom)->create();
        Lesson::factory()->forSeries($series)->number(11)->create();

        $this->actingAs($this->teacher)
            ->post('/admin/licoes', $this->payload(['series_id' => $series->id, 'number' => 11]))
            ->assertSessionHasErrors('number');

        $this->actingAs($this->teacher)
            ->post('/admin/licoes', $this->payload(['number' => 11]))
            ->assertSessionHasNoErrors();
    }

    public function test_deleting_a_lesson_frees_planned_sundays_but_keeps_history(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->published()->on('2026-09-13')->create();
        ClassMeeting::factory()->forLesson($lesson)->on('2026-09-20')->held()->create();
        $planned = ClassMeeting::factory()->forLesson($lesson)->on('2026-10-04')->create();

        $this->actingAs($this->teacher)->delete("/admin/licoes/{$lesson->id}")->assertRedirect();

        $this->assertSoftDeleted($lesson);
        $this->assertNull($planned->refresh()->lesson_id);
        $this->assertSame(2, ClassMeeting::query()->where('lesson_id', $lesson->id)->count());
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
