<?php

namespace Tests\Feature\Admin;

use App\Actions\Teaching\SetCurrentLesson;
use App\Enums\MeetingStatus;
use App\Enums\Weekday;
use App\Mcp\Servers\EbdServer;
use App\Mcp\Tools\GetClassroomOverview;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Página da classe na gestão (agenda), no fuso de São Paulo.
 */
class ClassroomOverviewTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'America/Sao_Paulo']);
        // Domingo, 27/09/2026, 10h em São Paulo (13h em UTC).
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 9, 27)->setTime(10, 0));

        $this->classroom = Classroom::factory()->create(['slug' => 'jovens', 'name' => 'Jovens']);
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
    }

    private function student(string $name, string $joinedOn = '2026-08-01', array $attributes = []): User
    {
        $user = User::factory()->create(['name' => $name, ...$attributes]);
        $this->classroom->members()->attach($user->id, ['role' => 'student', 'created_at' => "{$joinedOn} 15:00:00", 'updated_at' => "{$joinedOn} 15:00:00"]);

        return $user;
    }

    public function test_on_sunday_morning_the_week_card_is_today_with_the_weekly_message(): void
    {
        Lesson::factory()->for($this->classroom)->published()->number(16)->on('2026-09-27')->create(['title' => 'Temor Inquestionável']);
        Lesson::factory()->for($this->classroom)->published()->number(17)->on('2026-10-04')->create(['title' => 'Próxima']);

        $this->actingAs($this->teacher)->get('/admin/classes/jovens')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/classrooms/show')
                ->where('overview.week.meeting.held_on', '2026-09-27')
                ->where('overview.week.meeting.is_today', true)
                ->where('overview.week.can_take_attendance', true)
                ->where('overview.week.lesson.display_title', 'Lição 16 — Temor Inquestionável')
                ->where('overview.week.weekly_message', fn (string $text) => str_contains($text, 'Lição 16 — Temor Inquestionável')));
    }

    public function test_finishing_the_lesson_today_moves_the_card_to_the_next_lesson(): void
    {
        $series = Series::factory()->for($this->classroom)->create();
        $today = Lesson::factory()->forSeries($series)->published()->number(16)->on('2026-09-27')->create(['title' => 'Temor Inquestionável']);
        Lesson::factory()->forSeries($series)->published()->number(17)->create(['title' => 'Próxima']);
        $meeting = $today->meetings()->firstOrFail();
        $meeting->forceFill(['attendance_taken_at' => now()])->save();

        $this->actingAs($this->teacher)
            ->post(route('admin.meetings.finish', $meeting), ['continues' => false, 'notes' => 'Paramos no II'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->teacher)->get('/admin/classes/jovens')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('current.display_title', 'Lição 17 — Próxima')
                ->where('overview.week.meeting.held_on', '2026-10-04')
                ->where('overview.week.meeting.is_today', false)
                ->where('overview.week.lesson.display_title', 'Lição 17 — Próxima')
                ->where('overview.week.weekly_message', fn (string $text) => str_contains($text, 'Lição 17 — Próxima'))
                ->where('overview.last_sunday.held_on', '2026-09-27')
                ->where('overview.last_sunday.notes', 'Paramos no II'));
    }

    /** A conversa rendeu: a mesma lição segue para o próximo domingo. */
    public function test_finishing_today_with_the_lesson_continuing_keeps_it_for_the_next_sunday(): void
    {
        $today = Lesson::factory()->for($this->classroom)->published()->on('2026-09-27')->create();

        $this->actingAs($this->teacher)
            ->post(route('admin.meetings.finish', $today->meetings()->firstOrFail()), ['continues' => true])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->teacher)->get('/admin/classes/jovens')
            ->assertInertia(fn (Assert $page) => $page
                ->where('current.id', $today->id)
                ->where('current.meetings_held', 1)
                ->where('overview.week.meeting.held_on', '2026-10-04')
                ->where('overview.week.meeting_index', 2));
    }

    /** A chamada marca o domingo como realizado, mas não encerra a aula. */
    public function test_taking_attendance_alone_keeps_today_on_the_card_until_finishing(): void
    {
        $series = Series::factory()->for($this->classroom)->create();
        $today = Lesson::factory()->forSeries($series)->published()->number(1)->on('2026-09-27')->create();
        $next = Lesson::factory()->forSeries($series)->published()->number(2)->create();
        $meeting = $today->meetings()->firstOrFail();
        $meeting->forceFill(['status' => MeetingStatus::Held, 'attendance_taken_at' => now()])->save();

        $this->actingAs($this->teacher)->get('/admin/classes/jovens')
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.week.meeting.held_on', '2026-09-27')
                ->where('overview.week.meeting.is_finished', false));

        $this->actingAs($this->teacher)
            ->post(route('admin.meetings.finish', $meeting), ['continues' => false])
            ->assertRedirect();

        $this->assertNotNull($meeting->fresh()?->finished_at);

        $this->actingAs($this->teacher)->get('/admin/classes/jovens')
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.week.meeting.held_on', '2026-10-04')
                ->where('overview.week.lesson.id', $next->id));
    }

    public function test_who_missed_the_last_sundays_needs_attention_with_the_streak(): void
    {
        $ana = $this->student('Ana');
        $this->student('Rafael', attributes: ['phone' => '11999990000']);

        foreach (['2026-09-13', '2026-09-20'] as $date) {
            $meeting = Lesson::factory()->for($this->classroom)->published()->on($date)->create()->meetings()->firstOrFail();
            $meeting->forceFill(['status' => MeetingStatus::Held, 'attendance_taken_at' => now()])->save();
            DB::table('attendances')->insert(['class_meeting_id' => $meeting->id, 'user_id' => $ana->id, 'created_at' => now()]);
        }

        $this->actingAs($this->teacher)->get('/admin/classes/jovens')
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.recent.0.held_on', '2026-09-20')
                ->where('overview.recent.0.present', 1)
                ->where('overview.attention', fn ($rows) => collect($rows)->contains(
                    fn (array $row) => $row['name'] === 'Rafael' && $row['phone'] === '11999990000' && $row['missed_in_a_row'] === 2,
                )));
    }

    public function test_up_next_follows_the_series_order_without_dates(): void
    {
        $series = Series::factory()->for($this->classroom)->create(['title' => 'Milagres']);
        $current = Lesson::factory()->forSeries($series)->published()->number(2)->on('2026-10-04')->create(['title' => 'Atual']);
        $studied = Lesson::factory()->forSeries($series)->published()->number(1)->create(['title' => 'Primeira', 'studied_at' => now()]);
        $five = Lesson::factory()->forSeries($series)->number(5)->create(['title' => 'Cinco']);
        $three = Lesson::factory()->forSeries($series)->published()->number(3)->create(['title' => 'Três']);
        $four = Lesson::factory()->forSeries($series)->published()->number(4)->create(['title' => 'Quatro']);
        Lesson::factory()->forSeries($series)->published()->number(6)->create(['title' => 'Seis']);

        $this->actingAs($this->teacher)->get('/admin/classes/jovens')
            ->assertInertia(fn (Assert $page) => $page
                ->where('current.id', $current->id)
                ->where('current.series.title', 'Milagres')
                ->where('header.current_series.title', 'Milagres')
                ->where('header.lesson_position', ['index' => 2, 'total' => 6])
                ->where('upNext', fn ($rows) => collect($rows)->pluck('id')->all() === [$three->id, $four->id, $five->id])
                ->where('upNext.2.status', 'draft'));

        $this->assertNotNull($studied->refresh()->studied_at);
    }

    public function test_the_menu_lists_only_the_classes_of_the_teacher(): void
    {
        Classroom::factory()->create(['name' => 'Outra classe']);

        $this->actingAs($this->teacher)->get('/admin/classes/jovens')
            ->assertInertia(fn (Assert $page) => $page
                ->where('current', null)
                ->where('upNext', [])
                ->where('adminClassrooms', [['slug' => 'jovens', 'name' => 'Jovens']]));
    }

    public function test_pending_sundays_are_past_ones_still_planned(): void
    {
        $pending = ClassMeeting::factory()->for($this->classroom)->on('2026-09-20')->create();
        ClassMeeting::factory()->for($this->classroom)->on('2026-09-13')->held()->create();

        $this->actingAs($this->teacher)->get('/admin/classes/jovens')
            ->assertInertia(fn (Assert $page) => $page
                ->has('overview.pending', 1)
                ->where('overview.pending.0.id', $pending->id));
    }

    /**
     * Lição de dois domingos tem uma semana só de leituras: quem leu o plano
     * inteiro não aparece como "dias sem leitura" no segundo domingo.
     */
    public function test_attention_skips_students_who_finished_the_current_reading_plan(): void
    {
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 9, 30)->setTime(10, 0));
        $lesson = Lesson::factory()->for($this->classroom)->published()->on('2026-09-20')->create(['title' => 'Dois domingos']);
        ClassMeeting::factory()->for($this->classroom)->forLesson($lesson)->on('2026-10-04')->create();
        app(SetCurrentLesson::class)->handle($this->classroom, $lesson);

        foreach ([Weekday::Monday, Weekday::Tuesday, Weekday::Wednesday] as $day) {
            LessonReading::factory()->for($lesson)->create(['weekday' => $day]);
        }

        $reader = $this->student('Leitora');
        $this->student('Parado');

        foreach ([1 => '2026-09-14', 2 => '2026-09-15', 3 => '2026-09-16'] as $weekday => $date) {
            DB::table('reading_checkins')->insert(['user_id' => $reader->id, 'lesson_id' => $lesson->id, 'weekday' => $weekday, 'read_on' => $date, 'created_at' => now()]);
        }

        $this->actingAs($this->teacher)->get('/admin/classes/jovens')
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.week.meeting_index', 2)
                ->where('overview.home_study.readers', 1)
                ->where('overview.home_study.expected', 2)
                ->has('overview.attention', 1)
                ->where('overview.attention.0.name', 'Parado')
                ->where('overview.attention.0.reasons', ['ainda não marcou leitura']));
    }

    public function test_a_lesson_without_reading_plan_still_flags_who_is_not_reading(): void
    {
        Lesson::factory()->for($this->classroom)->published()->on('2026-09-27')->create();
        $this->student('Parado');

        $this->actingAs($this->teacher)->get('/admin/classes/jovens')
            ->assertInertia(fn (Assert $page) => $page
                ->has('overview.attention', 1)
                ->where('overview.attention.0.reasons', ['ainda não marcou leitura']));
    }

    public function test_new_students_are_not_flagged(): void
    {
        $this->student('Novato', '2026-09-20');

        $this->actingAs($this->teacher)->get('/admin/classes/jovens')
            ->assertInertia(fn (Assert $page) => $page->has('overview.attention', 0));
    }

    public function test_birthdays_of_the_month(): void
    {
        $this->student('Hoje', attributes: ['birth_date' => '2005-09-27']);
        $this->student('Começo do mês', attributes: ['birth_date' => '1999-09-02']);
        $this->student('Outubro', attributes: ['birth_date' => '2001-10-10']);

        $this->actingAs($this->teacher)->get('/admin/classes/jovens')
            ->assertInertia(fn (Assert $page) => $page
                ->has('overview.birthdays', 2)
                ->where('overview.birthdays.0.name', 'Começo do mês')
                ->where('overview.birthdays.0.is_today', false)
                ->where('overview.birthdays.1.name', 'Hoje')
                ->where('overview.birthdays.1.is_today', true)
                ->where('overview.birthdays.1.turning', 21));
    }

    public function test_frequency_uses_the_current_series_and_the_last_sunday_keeps_where_we_stopped(): void
    {
        $ana = $this->student('Ana');
        $this->student('Bia');
        $lesson = Lesson::factory()->for($this->classroom)->published()->on('2026-09-20')->create();
        $meeting = ClassMeeting::query()->where('lesson_id', $lesson->id)->sole();
        $meeting->update(['notes' => 'Paramos no tópico 3']);
        Lesson::factory()->for($this->classroom)->published()->on('2026-10-04')->create();

        $this->actingAs($this->teacher)->put("/admin/encontros/{$meeting->id}/chamada", ['present' => [$ana->id], 'visitors' => 1]);

        $this->actingAs($this->teacher)->get('/admin/classes/jovens')
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.last_sunday.notes', 'Paramos no tópico 3')
                ->where('overview.last_sunday.present', 1)
                ->where('overview.stats.frequency.rate', 50)
                ->where('overview.stats.period.label', 'Últimos 3 meses'));

        $this->assertSame(MeetingStatus::Held, $meeting->refresh()->status);
    }

    public function test_only_teachers_of_the_class_see_the_overview(): void
    {
        $student = $this->student('Ana');
        $outsider = User::factory()->teacherOf(Classroom::factory()->create())->create();

        $this->actingAs($outsider)->get('/admin/classes/jovens')->assertForbidden();
        $this->actingAs($student)->get('/admin/classes/jovens')->assertForbidden();
    }

    public function test_mcp_overview_matches_the_screen(): void
    {
        $this->student('Aniversariante', attributes: ['birth_date' => '2000-09-27']);
        Lesson::factory()->for($this->classroom)->published()->on('2026-09-27')->create(['title' => 'Lição da semana']);

        EbdServer::actingAs($this->teacher)->tool(GetClassroomOverview::class, ['classroom' => 'jovens'])
            ->assertOk()
            ->assertSee(['Lição da semana', '"frequency_rate"', '"birthdays_this_month"', 'Aniversariante', '/admin/classes/jovens']);
    }
}
