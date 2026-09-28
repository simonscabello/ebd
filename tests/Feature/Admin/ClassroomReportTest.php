<?php

namespace Tests\Feature\Admin;

use App\Actions\Meetings\RecordAttendance;
use App\Enums\Gender;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Relatório da classe por série (revista).
 */
class ClassroomReportTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'America/Sao_Paulo']);
        // Quarta, 30/09/2026.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 9, 30)->setTime(10, 0));

        $this->classroom = Classroom::factory()->create(['slug' => 'jovens', 'name' => 'Jovens']);
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
    }

    private function student(string $name, string $joinedOn, ?Gender $gender): User
    {
        $user = User::factory()->create(['name' => $name, 'gender' => $gender]);
        $this->classroom->members()->attach($user->id, ['role' => 'student', 'created_at' => "{$joinedOn} 15:00:00", 'updated_at' => "{$joinedOn} 15:00:00"]);

        return $user;
    }

    /**
     * @param  list<User>  $present
     */
    private function lessonOn(Series $series, string $date, array $present): Lesson
    {
        $lesson = Lesson::factory()->forSeries($series)->published()->on($date)->create();
        $meeting = ClassMeeting::query()->where('lesson_id', $lesson->id)->sole();
        app(RecordAttendance::class)->handle($meeting, array_map(fn (User $u) => $u->id, $present), 1, $this->teacher);

        return $lesson;
    }

    public function test_default_is_the_series_of_the_week_with_cells_blank_before_joining(): void
    {
        $series = Series::factory()->for($this->classroom)->create(['title' => 'Milagres', 'starts_on' => null, 'ends_on' => null]);
        $ana = $this->student('Ana', '2026-08-01', Gender::Female);
        $bruno = $this->student('Bruno', '2026-08-01', Gender::Male);
        $caio = $this->student('Caio', '2026-09-15', null);

        $this->lessonOn($series, '2026-09-13', [$ana]);
        $this->lessonOn($series, '2026-09-20', [$ana, $bruno, $caio]);
        Lesson::factory()->forSeries($series)->published()->on('2026-10-04')->create();

        $this->actingAs($this->teacher)->get('/admin/classes/jovens/relatorio')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/classrooms/report')
                ->where('selected', "serie:{$series->id}")
                ->where('period.label', 'Milagres')
                ->where('period.from', '2026-09-13')
                ->where('period.to', '2026-09-30')
                ->has('sundays', 2)
                ->where('totals.present', 4)
                ->where('totals.expected', 5)
                ->where('totals.visitors', 2)
                ->where('students.0.name', 'Ana')
                ->where('students.0.cells', [true, true])
                ->where('students.1.name', 'Bruno')
                ->where('students.1.cells', [false, true])
                ->where('students.2.name', 'Caio')
                ->where('students.2.cells', [null, true])
                ->where('byGender.0.label', 'Masculino')
                ->where('byGender.0.rate', 50)
                ->where('byGender.1.label', 'Feminino')
                ->where('byGender.1.rate', 100)
                ->where('byGender.2.label', 'Não informado'));
    }

    public function test_before_the_new_series_starts_the_numbers_stay_on_the_previous_one(): void
    {
        $old = Series::factory()->for($this->classroom)->create(['title' => 'Milagres', 'starts_on' => '2026-07-01', 'ends_on' => '2026-09-30']);
        $new = Series::factory()->for($this->classroom)->create(['title' => 'Parábolas', 'starts_on' => '2026-10-01', 'ends_on' => null]);
        $ana = $this->student('Ana', '2026-08-01', Gender::Female);
        $this->lessonOn($old, '2026-09-27', [$ana]);
        Lesson::factory()->forSeries($new)->published()->on('2026-10-04')->create();

        $this->actingAs($this->teacher)->get('/admin/classes/jovens/relatorio')
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected', "serie:{$old->id}")
                ->has('sundays', 1));

        $this->actingAs($this->teacher)->get("/admin/classes/jovens/alunos/{$ana->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.period.label', 'Milagres')
                ->where('stats.frequency.rate', 100));
    }

    public function test_a_series_can_be_chosen_and_the_last_months_are_available(): void
    {
        $old = Series::factory()->for($this->classroom)->create(['title' => 'Antiga', 'starts_on' => '2026-06-01', 'ends_on' => '2026-08-31']);
        $ana = $this->student('Ana', '2026-05-01', Gender::Female);
        $this->lessonOn($old, '2026-08-30', [$ana]);

        $this->actingAs($this->teacher)->get("/admin/classes/jovens/relatorio?serie={$old->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.from', '2026-06-01')
                ->where('period.to', '2026-08-31')
                ->has('sundays', 1));

        $this->actingAs($this->teacher)->get('/admin/classes/jovens/relatorio?periodo=3m')
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected', 'periodo:3m')
                ->where('period.label', 'Últimos 3 meses'));

        $other = Series::factory()->for(Classroom::factory()->create())->create();
        $this->actingAs($this->teacher)->get("/admin/classes/jovens/relatorio?serie={$other->id}")->assertNotFound();
    }

    public function test_without_series_it_shows_the_last_three_months(): void
    {
        $this->actingAs($this->teacher)->get('/admin/classes/jovens/relatorio')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.label', 'Últimos 3 meses')
                ->has('sundays', 0));
    }

    public function test_only_teachers_of_the_class_see_the_report(): void
    {
        $this->actingAs(User::factory()->teacherOf(Classroom::factory()->create())->create())
            ->get('/admin/classes/jovens/relatorio')
            ->assertForbidden();
    }
}
