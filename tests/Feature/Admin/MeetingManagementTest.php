<?php

namespace Tests\Feature\Admin;

use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Agenda da classe: domingos, lições em cada um, "sem EBD" e "continua".
 */
class MeetingManagementTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'Europe/Madrid']);
        // Quarta-feira, 23/09/2026.
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 23)->setTime(10, 0));

        $this->classroom = Classroom::factory()->create(['slug' => 'adultos']);
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
    }

    /**
     * @return list<string|null> títulos das lições por domingo, em ordem
     */
    private function agenda(): array
    {
        return ClassMeeting::query()
            ->whereBelongsTo($this->classroom)
            ->chronological()
            ->with('lesson')
            ->get()
            ->map(fn (ClassMeeting $m) => $m->isCancelled() ? 'SEM EBD' : $m->lesson?->title)
            ->all();
    }

    public function test_plan_quarter_creates_sundays_and_distributes_series_lessons_by_number(): void
    {
        $series = Series::factory()->for($this->classroom)->create();
        Lesson::factory()->forSeries($series)->number(2)->create(['title' => 'L2']);
        Lesson::factory()->forSeries($series)->number(1)->create(['title' => 'L1']);
        Lesson::factory()->forSeries($series)->number(3)->create(['title' => 'L3']);

        $this->actingAs($this->teacher)->post('/admin/classes/adultos/agenda/planejar', [
            'from' => '2026-09-24',
            'to' => '2026-10-25',
            'series_id' => $series->id,
        ])->assertSessionHasNoErrors();

        // Domingos: 27/09, 04/10, 11/10, 18/10, 25/10.
        $this->assertSame(['L1', 'L2', 'L3', null, null], $this->agenda());
        $this->assertSame('2026-09-27', Lesson::query()->where('title', 'L1')->sole()->scheduled_for?->toDateString());

        // Planejar de novo não duplica domingos nem lições.
        $this->actingAs($this->teacher)->post('/admin/classes/adultos/agenda/planejar', [
            'from' => '2026-09-24', 'to' => '2026-10-25', 'series_id' => $series->id,
        ]);
        $this->assertCount(5, $this->agenda());
    }

    public function test_only_one_meeting_per_day_and_lesson_must_be_of_the_classroom(): void
    {
        ClassMeeting::factory()->for($this->classroom)->on('2026-09-27')->create();
        $foreign = Lesson::factory()->create();

        $this->actingAs($this->teacher)
            ->post('/admin/classes/adultos/agenda', ['held_on' => '2026-09-27'])
            ->assertSessionHasErrors('held_on');

        $this->actingAs($this->teacher)
            ->post('/admin/classes/adultos/agenda', ['held_on' => '2026-10-04', 'lesson_id' => $foreign->id])
            ->assertSessionHasErrors('lesson_id');
    }

    public function test_sunday_without_ebd_pushes_the_lessons_forward(): void
    {
        $l1 = Lesson::factory()->for($this->classroom)->on('2026-09-27')->create(['title' => 'L1']);
        Lesson::factory()->for($this->classroom)->on('2026-10-04')->create(['title' => 'L2']);
        Lesson::factory()->for($this->classroom)->on('2026-10-11')->create(['title' => 'L3']);
        $meeting = ClassMeeting::query()->where('lesson_id', $l1->id)->sole();

        $this->actingAs($this->teacher)
            ->post("/admin/encontros/{$meeting->id}/cancelar", ['reason' => 'Culto de Missões', 'shift' => true])
            ->assertSessionHasNoErrors();

        // L3 ficou sem domingo: o professor é avisado.
        $this->assertSame(['SEM EBD', 'L1', 'L2'], $this->agenda());
        $this->assertSame('Culto de Missões', $meeting->refresh()->title);
        $this->assertSame('2026-10-04', $l1->refresh()->scheduled_for?->toDateString());
        $this->assertNull(Lesson::query()->where('title', 'L3')->sole()->scheduled_for);
    }

    public function test_cancel_without_shift_leaves_other_sundays_untouched(): void
    {
        $l1 = Lesson::factory()->for($this->classroom)->on('2026-09-27')->create(['title' => 'L1']);
        Lesson::factory()->for($this->classroom)->on('2026-10-04')->create(['title' => 'L2']);
        $meeting = ClassMeeting::query()->where('lesson_id', $l1->id)->sole();

        $this->actingAs($this->teacher)->post("/admin/encontros/{$meeting->id}/cancelar", ['shift' => false]);

        $this->assertSame(['SEM EBD', 'L2'], $this->agenda());
        $this->assertNull($l1->refresh()->scheduled_for);
    }

    public function test_lesson_continues_on_the_next_sunday(): void
    {
        $temor = Lesson::factory()->for($this->classroom)->on('2026-09-20')->create(['title' => 'Temor']);
        Lesson::factory()->for($this->classroom)->on('2026-09-27')->create(['title' => 'L2']);
        $meeting = ClassMeeting::query()->where('lesson_id', $temor->id)->sole();

        $this->actingAs($this->teacher)->post("/admin/encontros/{$meeting->id}/continuar")->assertSessionHasNoErrors();

        $this->assertSame(['Temor', 'Temor'], $this->agenda());
        $this->assertSame(MeetingStatus::Held, $meeting->refresh()->status);

        // Repetir não empurra de novo.
        $this->actingAs($this->teacher)->post("/admin/encontros/{$meeting->id}/continuar");
        $this->assertSame(['Temor', 'Temor'], $this->agenda());
    }

    public function test_continue_creates_the_next_sunday_when_agenda_is_empty(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->on('2026-09-20')->create(['title' => 'Temor']);
        $meeting = ClassMeeting::query()->where('lesson_id', $lesson->id)->sole();

        $this->actingAs($this->teacher)->post("/admin/encontros/{$meeting->id}/continuar");

        $this->assertDatabaseHas('class_meetings', ['lesson_id' => $lesson->id, 'held_on' => '2026-09-27', 'status' => 'planned']);
    }

    public function test_future_meeting_cannot_be_marked_as_held_or_continued(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->on('2026-10-04')->create();
        $meeting = ClassMeeting::query()->where('lesson_id', $lesson->id)->sole();

        $this->actingAs($this->teacher)->post("/admin/encontros/{$meeting->id}/realizado");
        $this->actingAs($this->teacher)->post("/admin/encontros/{$meeting->id}/continuar")->assertSessionHasErrors('meeting');

        $this->assertSame(MeetingStatus::Planned, $meeting->refresh()->status);
    }

    public function test_meeting_with_attendance_cannot_be_cancelled_or_deleted(): void
    {
        $meeting = ClassMeeting::factory()->for($this->classroom)->on('2026-09-20')->held()->create();
        $meeting->forceFill(['attendance_taken_at' => now()])->save();

        $this->actingAs($this->teacher)->post("/admin/encontros/{$meeting->id}/cancelar")->assertSessionHasErrors('meeting');
        $this->actingAs($this->teacher)->delete("/admin/encontros/{$meeting->id}");

        $this->assertModelExists($meeting);
        $this->assertSame(MeetingStatus::Held, $meeting->refresh()->status);
    }

    public function test_teacher_of_another_classroom_cannot_manage_the_agenda(): void
    {
        $outsider = User::factory()->teacherOf(Classroom::factory()->create())->create();
        $meeting = ClassMeeting::factory()->for($this->classroom)->create();

        $this->actingAs($outsider)->get('/admin/classes/adultos/agenda')->assertForbidden();
        $this->actingAs($outsider)->post('/admin/classes/adultos/agenda', ['held_on' => '2026-10-04'])->assertForbidden();
        $this->actingAs($outsider)->post("/admin/encontros/{$meeting->id}/cancelar")->assertForbidden();
        $this->actingAs($outsider)->delete("/admin/encontros/{$meeting->id}")->assertForbidden();
    }

    public function test_agenda_page_lists_meetings_with_notes_and_weekly_message(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->published()->number(11)->on('2026-09-27')->create([
            'title' => 'É Necessário',
            'bible_reference' => 'Jo 9.1-41',
        ]);
        ClassMeeting::query()->where('lesson_id', $lesson->id)->update(['notes' => 'Paramos no II.2']);

        $this->actingAs($this->teacher)->get('/admin/classes/adultos/agenda')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/classrooms/agenda')
                ->where('meetings.0.notes', 'Paramos no II.2')
                ->where('meetings.0.lesson.display_title', 'Lição 11 — É Necessário')
                ->where('weeklyMessage', fn (string $text) => str_contains($text, 'Lição 11 — É Necessário') && str_contains($text, 'Jo 9.1-41')));
    }
}
