<?php

namespace Tests\Feature\Admin;

use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Chamada no Modo Domingo, encerrar aula e o Resumo da classe.
 */
class AttendanceAndInsightsTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'Europe/Madrid']);
        // Domingo, 27/09/2026, durante a EBD.
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 27)->setTime(10, 0));

        $this->classroom = Classroom::factory()->create(['slug' => 'adultos']);
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
    }

    /**
     * Aluno que entrou na classe numa data específica.
     */
    private function student(string $name, string $joinedOn = '2026-08-01'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->classroom->members()->attach($user->id, ['role' => 'student', 'created_at' => $joinedOn, 'updated_at' => $joinedOn]);

        return $user;
    }

    private function meetingFor(Lesson $lesson): ClassMeeting
    {
        return ClassMeeting::query()->where('lesson_id', $lesson->id)->sole();
    }

    public function test_attendance_syncs_the_full_list_and_marks_the_meeting_as_held(): void
    {
        $ana = $this->student('Ana');
        $bia = $this->student('Bia');
        $lesson = Lesson::factory()->for($this->classroom)->published()->on('2026-09-27')->create(['slug' => 'hoje']);
        $meeting = $this->meetingFor($lesson);

        $this->actingAs($this->teacher)->put("/admin/encontros/{$meeting->id}/chamada", ['present' => [$ana->id, $bia->id], 'visitors' => 2])->assertSessionHasNoErrors();
        $this->actingAs($this->teacher)->put("/admin/encontros/{$meeting->id}/chamada", ['present' => [$ana->id], 'visitors' => 1]);
        $this->actingAs($this->teacher)->put("/admin/encontros/{$meeting->id}/chamada", ['present' => [$ana->id], 'visitors' => 1]);

        $meeting->refresh();
        $this->assertSame([$ana->id], $meeting->attendances()->pluck('user_id')->all());
        $this->assertSame(1, $meeting->visitors_count);
        $this->assertSame(MeetingStatus::Held, $meeting->status);
        $this->assertNotNull($meeting->attendance_taken_at);
    }

    public function test_attendance_rejects_non_students_future_and_cancelled_meetings(): void
    {
        $outsider = User::factory()->create();
        $lesson = Lesson::factory()->for($this->classroom)->published()->on('2026-09-27')->create();
        $future = ClassMeeting::factory()->for($this->classroom)->on('2026-10-04')->create();
        $cancelled = ClassMeeting::factory()->for($this->classroom)->on('2026-09-20')->cancelled()->create();

        $this->actingAs($this->teacher)->put("/admin/encontros/{$this->meetingFor($lesson)->id}/chamada", ['present' => [$outsider->id, $this->teacher->id]])->assertSessionHasErrors('present');
        $this->actingAs($this->teacher)->put("/admin/encontros/{$future->id}/chamada", ['present' => []])->assertSessionHasErrors('meeting');
        $this->actingAs($this->teacher)->put("/admin/encontros/{$cancelled->id}/chamada", ['present' => []])->assertSessionHasErrors('meeting');

        $other = User::factory()->teacherOf(Classroom::factory()->create())->create();
        $this->actingAs($other)->put("/admin/encontros/{$this->meetingFor($lesson)->id}/chamada", ['present' => []])->assertForbidden();
    }

    public function test_finish_meeting_with_continue_moves_lesson_to_next_sunday(): void
    {
        $temor = Lesson::factory()->for($this->classroom)->published()->on('2026-09-27')->create(['title' => 'Temor']);
        $next = Lesson::factory()->for($this->classroom)->published()->on('2026-10-04')->create(['title' => 'Próxima']);

        $this->actingAs($this->teacher)
            ->post("/admin/encontros/{$this->meetingFor($temor)->id}/encerrar", ['continues' => true, 'notes' => 'Paramos no véu rasgado'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Paramos no véu rasgado', ClassMeeting::query()->whereDate('held_on', '2026-09-27')->value('notes'));
        $this->assertSame($temor->id, ClassMeeting::query()->whereDate('held_on', '2026-10-04')->value('lesson_id'));
        $this->assertNull($next->refresh()->scheduled_for);
    }

    public function test_sunday_mode_gives_the_roster_only_to_teachers(): void
    {
        $ana = $this->student('Ana');
        Lesson::factory()->for($this->classroom)->published()->on('2026-09-27')->create(['slug' => 'hoje']);

        $this->actingAs($this->teacher)->get('/licoes/hoje/domingo')
            ->assertInertia(fn (Assert $page) => $page
                ->where('conduct.can_take_attendance', true)
                ->where('conduct.roster.0.name', 'Ana')
                ->where('conduct.meeting.held_on', '2026-09-27'));

        $this->actingAs($ana)->get('/licoes/hoje/domingo')
            ->assertInertia(fn (Assert $page) => $page->where('conduct', null));
        $this->get('/licoes/hoje/domingo')
            ->assertInertia(fn (Assert $page) => $page->where('conduct', null));
    }

    public function test_overview_respects_join_dates_and_flags_students_needing_attention(): void
    {
        $ana = $this->student('Ana', '2026-08-01');
        $bia = $this->student('Bia', '2026-08-01');
        $caio = $this->student('Caio', '2026-09-26'); // entrou ontem: não conta falta antes e não é "em risco"

        foreach (['2026-09-13', '2026-09-20'] as $date) {
            $lesson = Lesson::factory()->for($this->classroom)->published()->on($date)->create();
            $meeting = $this->meetingFor($lesson);
            $this->actingAs($this->teacher)->put("/admin/encontros/{$meeting->id}/chamada", ['present' => [$ana->id]]);
        }

        DB::table('reading_checkins')->insert(['user_id' => $ana->id, 'lesson_id' => $lesson->id, 'weekday' => 5, 'read_on' => '2026-09-25', 'created_at' => now()]);

        $this->actingAs($this->teacher)->get('/admin/classes/adultos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/classrooms/show')
                ->where('overview.stats.students', 3)
                ->where('overview.week', null)
                ->where('overview.last_sunday.held_on', '2026-09-20')
                ->where('overview.last_sunday.present', 1)
                ->where('overview.last_sunday.expected', 2)
                ->where('overview.last_sunday.rate', 50)
                ->has('overview.attention', 1)
                ->where('overview.attention.0.name', 'Bia')
                ->where('overview.attention.0.missed_in_a_row', 2));
    }

    public function test_overview_and_student_pages_are_restricted(): void
    {
        $ana = $this->student('Ana');
        $outsiderTeacher = User::factory()->teacherOf(Classroom::factory()->create())->create();
        $notMember = User::factory()->create();

        $this->actingAs($outsiderTeacher)->get('/admin/classes/adultos')->assertForbidden();
        $this->actingAs($outsiderTeacher)->get("/admin/classes/adultos/alunos/{$ana->id}")->assertForbidden();
        $this->actingAs($ana)->get('/admin/classes/adultos')->assertForbidden();

        $this->actingAs($this->teacher)->get("/admin/classes/adultos/alunos/{$notMember->id}")->assertNotFound();
        $this->actingAs($this->teacher)->get("/admin/classes/adultos/alunos/{$ana->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/classrooms/students/show')->where('student.name', 'Ana'));
    }

    public function test_perfect_attendance_badge_and_the_series_report(): void
    {
        $ana = $this->student('Ana');
        $bia = $this->student('Bia');
        $series = Series::factory()->for($this->classroom)->create(['title' => 'Milagres']);

        foreach (['2026-09-06', '2026-09-13', '2026-09-20', '2026-09-27'] as $date) {
            $lesson = Lesson::factory()->forSeries($series)->published()->on($date)->create();
            $this->actingAs($this->teacher)->put("/admin/encontros/{$this->meetingFor($lesson)->id}/chamada", [
                'present' => $date === '2026-09-13' ? [$ana->id] : [$ana->id, $bia->id],
            ]);
        }

        $this->assertDatabaseHas('user_badges', ['user_id' => $ana->id, 'badge' => 'perfect_attendance', 'series_id' => $series->id]);
        $this->assertDatabaseMissing('user_badges', ['user_id' => $bia->id, 'badge' => 'perfect_attendance']);

        // O endereço antigo do relatório da série leva ao relatório da classe.
        $this->actingAs($this->teacher)->get("/admin/series/{$series->id}/relatorio")
            ->assertStatus(301)
            ->assertRedirect("/admin/classes/adultos/relatorio?serie={$series->id}");

        $this->actingAs($this->teacher)->get("/admin/classes/adultos/relatorio?serie={$series->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/classrooms/report')
                ->where('totals.sundays', 4)
                ->where('students.0.name', 'Ana')
                ->where('students.0.present', 4)
                ->where('students.1.present', 3));

        $this->actingAs(User::factory()->teacherOf(Classroom::factory()->create())->create())
            ->get("/admin/classes/adultos/relatorio?serie={$series->id}")
            ->assertForbidden();
    }
}
