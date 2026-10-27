<?php

namespace Tests\Feature\Admin;

use App\Actions\Meetings\SyncUpcomingMeeting;
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

    public function test_the_next_meeting_goes_to_the_classroom_day(): void
    {
        $this->classroom->forceFill(['current_lesson_id' => Lesson::factory()->for($this->classroom)->create()->id])->save();

        app(SyncUpcomingMeeting::class)->handle($this->classroom);

        // Quinta: a quarta desta semana já passou, então é a da semana que vem.
        $this->assertSame(['2026-10-07'], $this->dates());
    }

    public function test_meeting_on_another_day_is_rejected(): void
    {
        $meeting = ClassMeeting::factory()->for($this->classroom)->on('2026-10-07')->create();

        $this->actingAs($this->teacher)
            ->put("/admin/encontros/{$meeting->id}", ['held_on' => '2026-10-04'])
            ->assertSessionHasErrors(['held_on' => 'Esta classe se reúne às quartas-feiras.']);

        $this->assertSame(['2026-10-07'], $this->dates());
    }

    public function test_sunday_and_saturday_messages_use_aos(): void
    {
        $this->assertSame('Esta classe se reúne aos domingos.', Weekday::Sunday->meetingDayMessage());
        $this->assertSame('Esta classe se reúne aos sábados.', Weekday::Saturday->meetingDayMessage());
        $this->assertSame('Esta classe se reúne às segundas-feiras.', Weekday::Monday->meetingDayMessage());
    }

    public function test_save_meeting_rejects_a_day_outside_the_classroom_day(): void
    {
        EbdServer::actingAs($this->teacher)
            ->tool(SaveMeetingTool::class, ['classroom' => 'quarta', 'held_on' => '2026-10-04'])
            ->assertHasErrors(['Esta classe se reúne às quartas-feiras.']);

        $this->assertSame([], $this->dates());
    }
}
