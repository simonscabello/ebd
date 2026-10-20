<?php

namespace Tests\Feature\Admin;

use App\Actions\Meetings\FillSundays;
use App\Enums\Weekday;
use App\Mcp\Servers\EbdServer;
use App\Mcp\Tools\SaveMeetingTool;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use App\Support\ChurchCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A agenda segue o dia da turma: geração, "planejar", "continua" e a recusa
 * de datas em outro dia.
 */
class MeetingWeekdayTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'America/Sao_Paulo']);
        // Quinta-feira, 01/10/2026.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 10, 1)->setTime(10, 0));

        $this->classroom = Classroom::factory()->create(['slug' => 'quarta', 'meeting_weekday' => Weekday::Wednesday]);
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
    }

    /**
     * @return list<string>
     */
    private function dates(): array
    {
        return ClassMeeting::query()->whereBelongsTo($this->classroom)->orderBy('held_on')->pluck('held_on')->map->toDateString()->all();
    }

    public function test_next_meeting_day(): void
    {
        $this->assertSame('2026-10-01', ChurchCalendar::nextMeetingDay(Weekday::Thursday)->toDateString());
        $this->assertSame('2026-10-07', ChurchCalendar::nextMeetingDay(Weekday::Wednesday)->toDateString());
        $this->assertSame('2026-10-04', ChurchCalendar::nextMeetingDay(Weekday::Sunday)->toDateString());
    }

    public function test_fill_creates_meetings_on_the_classroom_day(): void
    {
        app(FillSundays::class)->handle($this->classroom);

        $dates = $this->dates();
        $this->assertSame('2026-09-30', $dates[0]);
        $this->assertCount(FillSundays::WEEKS_AHEAD + 1, $dates);
        foreach ($dates as $date) {
            $this->assertSame(3, now()->parse($date)->dayOfWeekIso, $date);
        }
    }

    public function test_plan_meetings_uses_the_classroom_day(): void
    {
        $this->actingAs($this->teacher)
            ->post('/admin/classes/quarta/domingos/planejar', ['from' => '2026-10-01', 'to' => '2026-10-31'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['2026-10-07', '2026-10-14', '2026-10-21', '2026-10-28'], $this->dates());
    }

    public function test_meeting_on_another_day_is_rejected(): void
    {
        $meeting = ClassMeeting::factory()->for($this->classroom)->on('2026-10-07')->create();

        $this->actingAs($this->teacher)
            ->put("/admin/encontros/{$meeting->id}", ['held_on' => '2026-10-04'])
            ->assertSessionHasErrors(['held_on' => 'Esta classe se reúne às quartas-feiras.']);

        $this->actingAs($this->teacher)
            ->post('/admin/licoes', [
                'classroom_id' => $this->classroom->id,
                'title' => 'Fora do dia',
                'visibility' => 'public',
                'meeting_on' => '2026-10-04',
            ])
            ->assertSessionHasErrors(['meeting_on' => 'Esta classe se reúne às quartas-feiras.']);

        $this->assertSame(['2026-10-07'], $this->dates());
    }

    public function test_sunday_and_saturday_messages_use_aos(): void
    {
        $this->assertSame('Esta classe se reúne aos domingos.', Weekday::Sunday->meetingDayMessage());
        $this->assertSame('Esta classe se reúne aos sábados.', Weekday::Saturday->meetingDayMessage());
        $this->assertSame('Esta classe se reúne às segundas-feiras.', Weekday::Monday->meetingDayMessage());
    }

    public function test_continue_creates_the_next_meeting_on_the_classroom_day(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->published()->on('2026-09-30')->create();
        $meeting = ClassMeeting::query()->where('lesson_id', $lesson->id)->sole();

        $this->actingAs($this->teacher)
            ->post("/admin/encontros/{$meeting->id}/encerrar", ['continues' => true])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(['2026-09-30', '2026-10-07'], $this->dates());
        $this->assertSame($lesson->id, ClassMeeting::query()->whereDate('held_on', '2026-10-07')->value('lesson_id'));
    }

    public function test_save_meeting_rejects_a_day_outside_the_classroom_day(): void
    {
        EbdServer::actingAs($this->teacher)
            ->tool(SaveMeetingTool::class, ['classroom' => 'quarta', 'held_on' => '2026-10-04'])
            ->assertHasErrors(['Esta classe se reúne às quartas-feiras.']);

        $this->assertSame([], $this->dates());
    }

    public function test_plan_includes_a_last_day_that_is_a_meeting_day(): void
    {
        $this->actingAs($this->teacher)
            ->post('/admin/classes/quarta/domingos/planejar', ['from' => '2026-10-01', 'to' => '2026-10-28'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['2026-10-07', '2026-10-14', '2026-10-21', '2026-10-28'], $this->dates());
    }

    public function test_plan_skips_a_week_that_already_has_a_meeting_on_the_old_day(): void
    {
        // A turma era de domingo e passou para quarta: o domingo 11/10 ficou.
        ClassMeeting::factory()->for($this->classroom)->on('2026-10-11')->create();

        $this->actingAs($this->teacher)
            ->post('/admin/classes/quarta/domingos/planejar', ['from' => '2026-10-01', 'to' => '2026-10-28'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['2026-10-07', '2026-10-11', '2026-10-21', '2026-10-28'], $this->dates());
    }

    public function test_lesson_on_a_taken_meeting_names_the_meeting_not_sunday(): void
    {
        Lesson::factory()->for($this->classroom)->on('2026-10-07')->create();

        $this->actingAs($this->teacher)
            ->post('/admin/licoes', [
                'classroom_id' => $this->classroom->id,
                'title' => 'Outra',
                'visibility' => 'public',
                'meeting_on' => '2026-10-07',
            ])
            ->assertSessionHasErrors(['meeting_on' => 'Já existe uma lição neste encontro. Ajuste pela agenda da classe.']);
    }
}
