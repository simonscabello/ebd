<?php

namespace Tests\Feature\Admin;

use App\Actions\Meetings\FillSundays;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
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

    public function test_plan_quarter_creates_sundays_and_distributes_series_lessons_by_number(): void
    {
        $series = Series::factory()->for($this->classroom)->create();
        Lesson::factory()->forSeries($series)->number(2)->create(['title' => 'L2']);
        Lesson::factory()->forSeries($series)->number(1)->create(['title' => 'L1']);
        Lesson::factory()->forSeries($series)->number(3)->create(['title' => 'L3']);

        $this->actingAs($this->teacher)->post('/admin/classes/adultos/domingos/planejar', [
            'from' => '2026-09-24',
            'to' => '2026-10-25',
            'series_id' => $series->id,
        ])->assertSessionHasNoErrors();

        // Domingos: 27/09, 04/10, 11/10, 18/10, 25/10.
        $this->assertSame(['L1', 'L2', 'L3', null, null], $this->agenda());
        $this->assertSame('2026-09-27', Lesson::query()->where('title', 'L1')->sole()->scheduled_for?->toDateString());

        // Planejar de novo não duplica domingos nem lições.
        $this->actingAs($this->teacher)->post('/admin/classes/adultos/domingos/planejar', [
            'from' => '2026-09-24', 'to' => '2026-10-25', 'series_id' => $series->id,
        ]);
        $this->assertCount(5, $this->agenda());
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

    public function test_every_sunday_is_on_the_agenda_from_the_first_one_to_weeks_ahead(): void
    {
        ClassMeeting::factory()->for($this->classroom)->on('2026-09-06')->held()->create();
        ClassMeeting::factory()->for($this->classroom)->on('2026-09-20')->cancelled()->create(['title' => 'Retiro']);

        $this->actingAs($this->teacher)->get('/admin/classes/adultos/domingos')->assertOk();

        $dates = ClassMeeting::query()->whereBelongsTo($this->classroom)->chronological()->pluck('held_on')->map->toDateString();

        // Do primeiro domingo (06/09) até 12 semanas depois do domingo desta semana (20/09).
        $this->assertSame('2026-09-06', $dates->first());
        $this->assertSame('2026-12-13', $dates->last());
        $this->assertCount(15, $dates);
        $this->assertSame('Retiro', ClassMeeting::query()->whereDate('held_on', '2026-09-20')->sole()->title);

        // Abrir de novo não duplica nada; domingo não se exclui, vira "sem EBD".
        $this->actingAs($this->teacher)->get('/admin/classes/adultos/domingos');
        $this->assertSame(15, ClassMeeting::query()->whereBelongsTo($this->classroom)->count());
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

        $current = app(CurrentLessonQuery::class)->for($this->classroom, $this->teacher);

        $this->assertSame($lesson->id, $current->lesson?->id);
    }

    public function test_old_agenda_and_evolution_addresses_redirect(): void
    {
        $this->actingAs($this->teacher)->get('/admin/classes/adultos/agenda')->assertRedirect('/admin/classes/adultos/domingos')->assertStatus(301);
        $this->actingAs($this->teacher)->get('/admin/classes/adultos/evolucao')->assertRedirect('/admin/classes/adultos')->assertStatus(301);
    }
}
