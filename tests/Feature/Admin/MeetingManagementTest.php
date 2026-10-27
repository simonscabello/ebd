<?php

namespace Tests\Feature\Admin;

use App\Actions\Meetings\FillSundays;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use App\Queries\CurrentLessonQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Domingos da classe: a lista, a página de cada domingo, a lição em cada um,
 * "sem EBD" (e desfazer) e "continua".
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

    public function test_only_one_meeting_per_day_and_lesson_must_be_of_the_classroom(): void
    {
        ClassMeeting::factory()->for($this->classroom)->on('2026-09-27')->create();
        $meeting = ClassMeeting::factory()->for($this->classroom)->on('2026-10-04')->create();
        $foreign = Lesson::factory()->create();

        $this->actingAs($this->teacher)
            ->put("/admin/encontros/{$meeting->id}", ['held_on' => '2026-09-27'])
            ->assertSessionHasErrors('held_on');

        $this->actingAs($this->teacher)
            ->put("/admin/encontros/{$meeting->id}", ['held_on' => '2026-10-04', 'lesson_id' => $foreign->id])
            ->assertSessionHasErrors('lesson_id');
    }

    public function test_sunday_without_ebd_sends_the_current_lesson_to_the_next_sunday(): void
    {
        $l1 = Lesson::factory()->for($this->classroom)->on('2026-09-27')->create(['title' => 'L1']);
        Lesson::factory()->for($this->classroom)->on('2026-10-04')->create(['title' => 'L2']);
        Lesson::factory()->for($this->classroom)->on('2026-10-11')->create(['title' => 'L3']);
        $meeting = ClassMeeting::query()->where('lesson_id', $l1->id)->sole();

        $this->actingAs($this->teacher)
            ->post("/admin/encontros/{$meeting->id}/cancelar", ['reason' => 'Culto de Missões'])
            ->assertSessionHasNoErrors();

        // A sequência vem da série, não da agenda: as datas adiante ficam sem lição.
        $this->assertSame(['SEM EBD', 'L1', null], $this->agenda());
        $this->assertSame('Culto de Missões', $meeting->refresh()->title);
        $this->assertSame('2026-10-04', $l1->refresh()->scheduled_for?->toDateString());
    }

    public function test_future_meeting_cannot_be_marked_as_held(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->on('2026-10-04')->create();
        $meeting = ClassMeeting::query()->where('lesson_id', $lesson->id)->sole();

        $this->actingAs($this->teacher)->post("/admin/encontros/{$meeting->id}/realizado");

        $this->assertSame(MeetingStatus::Planned, $meeting->refresh()->status);
    }

    public function test_meeting_with_attendance_cannot_be_cancelled(): void
    {
        $meeting = ClassMeeting::factory()->for($this->classroom)->on('2026-09-20')->held()->create();
        $meeting->forceFill(['attendance_taken_at' => now()])->save();

        $this->actingAs($this->teacher)->post("/admin/encontros/{$meeting->id}/cancelar")->assertSessionHasErrors('meeting');
        $this->assertSame(MeetingStatus::Held, $meeting->refresh()->status);
    }

    public function test_teacher_of_another_classroom_cannot_manage_the_agenda(): void
    {
        $outsider = User::factory()->teacherOf(Classroom::factory()->create())->create();
        $meeting = ClassMeeting::factory()->for($this->classroom)->create();

        $this->actingAs($outsider)->get('/admin/classes/adultos/domingos')->assertForbidden();
        $this->actingAs($outsider)->get("/admin/classes/adultos/domingos/{$meeting->id}")->assertForbidden();
        $this->actingAs($outsider)->post("/admin/encontros/{$meeting->id}/cancelar")->assertForbidden();
    }

    public function test_sundays_page_lists_upcoming_and_past_with_attendance_summary(): void
    {
        $past = Lesson::factory()->for($this->classroom)->published()->number(10)->on('2026-09-20')->create(['title' => 'Temor']);
        Lesson::factory()->for($this->classroom)->published()->number(11)->on('2026-09-27')->create(['title' => 'É Necessário']);
        $ana = User::factory()->studentOf($this->classroom)->create(['name' => 'Ana']);
        User::factory()->studentOf($this->classroom)->create(['name' => 'Bia']);
        $this->classroom->members()->updateExistingPivot($ana->id, ['created_at' => '2026-08-01']);
        DB::table('classroom_user')->where('classroom_id', $this->classroom->id)->update(['created_at' => '2026-08-01']);

        $meeting = ClassMeeting::query()->where('lesson_id', $past->id)->sole();
        $this->actingAs($this->teacher)->put("/admin/encontros/{$meeting->id}/chamada", ['present' => [$ana->id], 'visitors' => 2]);

        $this->actingAs($this->teacher)->get('/admin/classes/adultos/domingos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/classrooms/meetings/index')
                ->where('upcoming.0.lesson.display_title', 'Lição 11 — É Necessário')
                ->where('past.0.held_on', '2026-09-20')
                ->where('past.0.summary', ['present' => 1, 'expected' => 2, 'visitors' => 2, 'rate' => 50]));
    }

    public function test_sunday_page_shows_lesson_attendance_and_where_we_stopped(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->published()->number(11)->on('2026-09-20')->create(['title' => 'É Necessário']);
        $meeting = ClassMeeting::query()->where('lesson_id', $lesson->id)->sole();
        $meeting->update(['notes' => 'Paramos no II.2']);
        $ana = User::factory()->studentOf($this->classroom)->create(['name' => 'Ana']);

        $this->actingAs($this->teacher)->get("/admin/classes/adultos/domingos/{$meeting->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/classrooms/meetings/show')
                ->where('meeting.notes', 'Paramos no II.2')
                ->where('meeting.can_take_attendance', true)
                ->where('attendance.roster.0.name', 'Ana')
                ->where('summary', null));

        // Chamada feita depois do dia, pela página do domingo.
        $this->actingAs($this->teacher)->put("/admin/encontros/{$meeting->id}/chamada", ['present' => [$ana->id]])->assertSessionHasNoErrors();
        $this->assertSame(MeetingStatus::Held, $meeting->refresh()->status);
    }

    public function test_sunday_of_another_classroom_is_not_found_under_this_one(): void
    {
        $other = Classroom::factory()->create(['slug' => 'jovens']);
        $foreign = ClassMeeting::factory()->for($other)->on('2026-09-27')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get("/admin/classes/adultos/domingos/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_attendance_on_a_sunday_without_lesson_marks_it_as_held(): void
    {
        $meeting = ClassMeeting::factory()->for($this->classroom)->on('2026-09-20')->create(['title' => 'Culto especial']);
        $student = User::factory()->studentOf($this->classroom)->create();

        $this->actingAs($this->teacher)
            ->put("/admin/encontros/{$meeting->id}/chamada", ['present' => [$student->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(MeetingStatus::Held, $meeting->refresh()->status);
        $this->assertTrue($meeting->hasAttendance());
    }

    public function test_cancelled_sunday_can_be_restored(): void
    {
        $meeting = ClassMeeting::factory()->for($this->classroom)->on('2026-10-04')->create();

        $this->actingAs($this->teacher)->post("/admin/encontros/{$meeting->id}/cancelar", ['reason' => 'Culto de Missões', 'shift' => false]);
        $this->assertSame(MeetingStatus::Cancelled, $meeting->refresh()->status);

        $this->actingAs($this->teacher)->post("/admin/encontros/{$meeting->id}/restaurar")->assertSessionHasNoErrors();

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Planned, $meeting->status);
        $this->assertNull($meeting->title);
    }

    public function test_cancelling_without_reason_keeps_the_event_title(): void
    {
        $meeting = ClassMeeting::factory()->for($this->classroom)->on('2026-10-04')->create(['title' => 'Revisão do trimestre']);

        $this->actingAs($this->teacher)->post("/admin/encontros/{$meeting->id}/cancelar", ['shift' => false]);

        $this->assertSame('Revisão do trimestre', $meeting->refresh()->title);
    }

    public function test_only_the_next_sunday_is_on_the_agenda_ahead(): void
    {
        ClassMeeting::factory()->for($this->classroom)->on('2026-09-06')->held()->create();
        ClassMeeting::factory()->for($this->classroom)->on('2026-09-27')->cancelled()->create(['title' => 'Retiro']);
        $lesson = Lesson::factory()->for($this->classroom)->create(['title' => 'Atual']);
        $this->classroom->forceFill(['current_lesson_id' => $lesson->id])->save();

        $this->actingAs($this->teacher)->get('/admin/classes/adultos/domingos')->assertOk();
        $this->actingAs($this->teacher)->get('/admin/classes/adultos/domingos')->assertOk();

        // O domingo de "Retiro" fica; a lição atual vai para o seguinte. Abrir de novo não duplica.
        $this->assertSame(['2026-09-06' => null, '2026-09-27' => 'SEM EBD', '2026-10-04' => 'Atual'], ClassMeeting::query()
            ->whereBelongsTo($this->classroom)->chronological()->with('lesson')->get()
            ->mapWithKeys(fn (ClassMeeting $m) => [$m->held_on->toDateString() => $m->isCancelled() ? 'SEM EBD' : $m->lesson?->title])
            ->all());
        $this->actingAs($this->teacher)->delete('/admin/encontros/'.ClassMeeting::query()->value('id'))->assertMethodNotAllowed();
    }

    public function test_new_classroom_starts_the_agenda_this_week(): void
    {
        app(FillSundays::class)->handle($this->classroom);

        $dates = ClassMeeting::query()->whereBelongsTo($this->classroom)->chronological()->pluck('held_on')->map->toDateString();

        $this->assertSame('2026-09-20', $dates->first());
        $this->assertCount(13, $dates);
    }

    public function test_lesson_of_the_week_skips_empty_sundays(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->published()->number(1)->on('2026-10-11')->create();
        app(FillSundays::class)->handle($this->classroom);

        $current = app(CurrentLessonQuery::class)->for($this->classroom->refresh(), $this->teacher);

        $this->assertSame($lesson->id, $current->lesson?->id);
        $this->assertSame('2026-10-11', $current->meeting?->held_on->toDateString());
    }

    public function test_old_agenda_and_evolution_addresses_redirect(): void
    {
        $this->actingAs($this->teacher)->get('/admin/classes/adultos/agenda')->assertRedirect('/admin/classes/adultos/domingos')->assertStatus(301);
        $this->actingAs($this->teacher)->get('/admin/classes/adultos/evolucao')->assertRedirect('/admin/classes/adultos')->assertStatus(301);
    }
}
