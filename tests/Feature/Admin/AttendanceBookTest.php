<?php

namespace Tests\Feature\Admin;

use App\Actions\Meetings\RecordAttendance;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\User;
use App\Queries\AttendanceBookQuery;
use App\Queries\Data\Period;
use App\Support\Enrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Livro de chamada: a regra única de quem conta em cada domingo. O fuso é o de
 * São Paulo (UTC-3): meia-noite UTC ainda é "ontem" aqui.
 */
class AttendanceBookTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'America/Sao_Paulo']);
        // Domingo, 27/09/2026, 10h em São Paulo.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 9, 27)->setTime(10, 0));

        $this->classroom = Classroom::factory()->create(['slug' => 'jovens']);
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
    }

    /**
     * Aluno que entrou na classe num instante UTC específico.
     */
    private function student(string $name, string $joinedAtUtc): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->classroom->members()->attach($user->id, ['role' => 'student', 'created_at' => $joinedAtUtc, 'updated_at' => $joinedAtUtc]);

        return $user;
    }

    /**
     * @param  list<User>  $present
     */
    private function sunday(string $date, array $present, int $visitors = 0): ClassMeeting
    {
        $meeting = ClassMeeting::factory()->for($this->classroom)->on($date)->create();
        app(RecordAttendance::class)->handle($meeting, array_map(fn (User $u) => $u->id, $present), $visitors, $this->teacher);

        return $meeting->refresh();
    }

    public function test_student_counts_from_the_day_after_joining_in_the_church_timezone(): void
    {
        // 23h de sábado em São Paulo = 02h de domingo em UTC: entrou no sábado.
        $saturday = $this->student('Sábado', '2026-09-27 02:00:00');
        // Entrou no domingo durante a aula: só conta naquele domingo se estiver na chamada.
        $sunday = $this->student('Domingo', '2026-09-27 13:30:00');

        $since = Enrollment::sinceMap($this->classroom->id);

        $this->assertSame('2026-09-27', $since[$saturday->id]);
        $this->assertSame('2026-09-28', $since[$sunday->id]);

        $meeting = $this->sunday('2026-09-27', []);
        $book = app(AttendanceBookQuery::class)->for($this->classroom);

        $this->assertFalse($book->cell($saturday->id, $meeting));
        $this->assertNull($book->cell($sunday->id, $meeting));
        $this->assertSame(['present' => 0, 'expected' => 1, 'visitors' => 0, 'rate' => 0], $book->sunday($meeting));
    }

    public function test_a_presence_before_joining_moves_the_start_back(): void
    {
        $late = $this->student('Tardio', '2026-09-24 15:00:00');
        $this->sunday('2026-09-20', [$late]);

        $this->assertSame('2026-09-20', Enrollment::sinceMap($this->classroom->id)[$late->id]);
    }

    public function test_rates_ignore_visitors_sundays_without_attendance_and_cancelled_ones(): void
    {
        $ana = $this->student('Ana', '2026-08-01 12:00:00');
        $bia = $this->student('Bia', '2026-08-01 12:00:00');

        $this->sunday('2026-09-13', [$ana], visitors: 5);
        $this->sunday('2026-09-20', [$ana, $bia]);
        ClassMeeting::factory()->for($this->classroom)->on('2026-09-06')->held()->create();
        ClassMeeting::factory()->for($this->classroom)->on('2026-08-30')->cancelled()->create();

        $book = app(AttendanceBookQuery::class)->for($this->classroom);

        $this->assertCount(2, $book->meetings);
        $this->assertSame(['sundays' => 2, 'present' => 3, 'expected' => 4, 'visitors' => 5, 'rate' => 75], $book->totals());
        $this->assertSame(['present' => 1, 'expected' => 2, 'rate' => 50, 'missed_in_a_row' => 0, 'last_present_on' => '2026-09-20'], $book->student($bia->id));
    }

    public function test_missed_in_a_row_counts_back_from_the_latest_sunday(): void
    {
        $ana = $this->student('Ana', '2026-08-01 12:00:00');
        $bia = $this->student('Bia', '2026-08-01 12:00:00');

        $this->sunday('2026-09-06', [$ana, $bia]);
        $this->sunday('2026-09-13', [$ana]);
        $this->sunday('2026-09-20', [$ana]);

        $book = app(AttendanceBookQuery::class)->for($this->classroom);

        $this->assertSame(2, $book->student($bia->id)['missed_in_a_row']);
        $this->assertSame('2026-09-06', $book->student($bia->id)['last_present_on']);
        $this->assertSame(0, $book->student($ana->id)['missed_in_a_row']);
        $this->assertSame(['2026-09-20', '2026-09-13', '2026-09-06'], array_column($book->history($bia->id), 'held_on'));
    }

    public function test_period_limits_the_sundays_and_totals(): void
    {
        $ana = $this->student('Ana', '2026-08-01 12:00:00');

        $this->sunday('2026-08-30', []);
        $this->sunday('2026-09-20', [$ana]);

        $book = app(AttendanceBookQuery::class)->for($this->classroom, new Period('2026-09-01', '2026-09-30', 'Setembro'));

        $this->assertCount(1, $book->meetings);
        $this->assertSame(100, $book->totals()['rate']);
        $this->assertSame(0, app(AttendanceBookQuery::class)->for($this->classroom)->student($ana->id)['missed_in_a_row']);
    }

    public function test_removed_students_leave_the_numbers_but_keep_their_attendance(): void
    {
        $ana = $this->student('Ana', '2026-08-01 12:00:00');
        $bia = $this->student('Bia', '2026-08-01 12:00:00');
        $meeting = $this->sunday('2026-09-27', [$ana, $bia]);

        $this->classroom->members()->detach($bia->id);

        // A chamada de hoje continua salvando só com quem ficou na classe.
        app(RecordAttendance::class)->handle($meeting, [$ana->id], null, $this->teacher);

        $this->assertDatabaseHas('attendances', ['class_meeting_id' => $meeting->id, 'user_id' => $bia->id]);
        $this->assertSame(['present' => 1, 'expected' => 1, 'visitors' => 0, 'rate' => 100], app(AttendanceBookQuery::class)->for($this->classroom)->sunday($meeting));
    }

    public function test_attendance_keeps_visitors_when_they_are_not_sent(): void
    {
        $ana = $this->student('Ana', '2026-08-01 12:00:00');
        $meeting = $this->sunday('2026-09-27', [$ana], visitors: 3);

        $this->actingAs($this->teacher)
            ->put("/admin/encontros/{$meeting->id}/chamada", ['present' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $meeting->refresh()->visitors_count);
    }

    public function test_attendance_opens_on_the_sunday_in_the_church_timezone(): void
    {
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 9, 26)->setTime(22, 30));
        $meeting = ClassMeeting::factory()->for($this->classroom)->on('2026-09-27')->create();

        $this->actingAs($this->teacher)
            ->put("/admin/encontros/{$meeting->id}/chamada", ['present' => []])
            ->assertSessionHasErrors('meeting');
    }
}
