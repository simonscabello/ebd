<?php

namespace Tests\Feature\Admin;

use App\Actions\Meetings\RecordAttendance;
use App\Enums\Weekday;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cada turma escolhe o dia da semana do encontro (padrão domingo).
 */
class ClassroomScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'America/Sao_Paulo']);
        // Domingo, 27/09/2026, 10h em São Paulo.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 9, 27)->setTime(10, 0));
    }

    private function payload(array $data = []): array
    {
        return ['name' => 'Casais', 'is_active' => true, ...$data];
    }

    public function test_new_classrooms_meet_on_sunday_by_default(): void
    {
        $this->assertSame(Weekday::Sunday, Classroom::factory()->create()->refresh()->meeting_weekday);

        $this->actingAs(User::factory()->admin()->create())->post('/admin/classes', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(Weekday::Sunday, Classroom::query()->where('name', 'Casais')->sole()->meeting_weekday);
    }

    public function test_admin_sets_the_meeting_weekday(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/classes', $this->payload(['meeting_weekday' => 3]))->assertSessionHasNoErrors();
        $this->assertSame(Weekday::Wednesday, Classroom::query()->where('name', 'Casais')->sole()->meeting_weekday);

        $this->actingAs($admin)->post('/admin/classes', $this->payload(['name' => 'Outra', 'meeting_weekday' => 8]))->assertSessionHasErrors('meeting_weekday');
    }

    public function test_changing_the_day_moves_planned_future_meetings_within_their_week(): void
    {
        $classroom = Classroom::factory()->create(['slug' => 'jovens']);
        $teacher = User::factory()->teacherOf($classroom)->create();
        $student = User::factory()->studentOf($classroom)->create();

        $held = ClassMeeting::factory()->for($classroom)->on('2026-09-20')->create();
        app(RecordAttendance::class)->handle($held, [$student->id], 0, $teacher);
        $a = Lesson::factory()->for($classroom)->published()->on('2026-10-04')->create();
        $cancelled = ClassMeeting::factory()->for($classroom)->on('2026-10-11')->cancelled()->create();
        $b = Lesson::factory()->for($classroom)->published()->on('2026-10-18')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put("/admin/classes/{$classroom->slug}", ['name' => $classroom->name, 'is_active' => true, 'meeting_weekday' => 3])
            ->assertSessionHasNoErrors();

        $dates = fn (int $lessonId) => ClassMeeting::query()->where('lesson_id', $lessonId)->pluck('held_on')->map->toDateString()->all();
        $this->assertSame(['2026-09-30'], $dates($a->id));
        $this->assertSame(['2026-10-14'], $dates($b->id));
        $this->assertSame('2026-09-20', $held->refresh()->held_on->toDateString());
        $this->assertSame('2026-10-11', $cancelled->refresh()->held_on->toDateString());
        $this->assertSame('2026-09-30', $a->refresh()->scheduled_for->toDateString());
        $this->assertSame(Weekday::Wednesday, $classroom->refresh()->meeting_weekday);
    }

    public function test_teacher_cannot_change_the_meeting_day(): void
    {
        $classroom = Classroom::factory()->create(['slug' => 'jovens']);
        $teacher = User::factory()->teacherOf($classroom)->create();

        $this->actingAs($teacher)
            ->put('/admin/classes/jovens', ['name' => $classroom->name, 'meeting_weekday' => 3])
            ->assertForbidden();

        $this->assertSame(Weekday::Sunday, $classroom->refresh()->meeting_weekday);
    }
}
