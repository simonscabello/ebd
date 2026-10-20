<?php

namespace Tests\Feature\Church;

use App\Models\Church;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\ChurchCalendar;
use App\Support\Push\PushSender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePushSender;
use Tests\TestCase;

/**
 * Cada igreja no seu fuso: "hoje" vira à meia-noite dela e os lembretes
 * chegam na hora local dela. A igreja do Simon (America/Sao_Paulo, o fuso da
 * configuração) continua exatamente como antes.
 */
class ChurchTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private FakePushSender $push;

    private User $brazil;

    private User $lisbon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->push = new FakePushSender;
        $this->app->instance(PushSender::class, $this->push);

        // Quarta-feira, 23/09/2026 (Lisboa no horário de verão, UTC+1; São Paulo, UTC-3).
        $this->travelTo(CarbonImmutable::parse('2026-09-23 00:00', 'UTC'));

        $simon = Classroom::factory()->create(['slug' => 'jovens-sp']); // a primeira igreja, no fuso da configuração
        $portugal = Classroom::factory()->for(Church::factory()->create(['timezone' => 'Europe/Lisbon']))->create(['slug' => 'jovens-lx']);

        $this->brazil = $this->memberOf($simon, 'sp');
        $this->lisbon = $this->memberOf($portugal, 'lx');
    }

    private function memberOf(Classroom $classroom, string $device): User
    {
        Lesson::factory()->for($classroom)->published()->on('2026-09-27')->create(['bible_reference' => 'Isaías 6.1-8']);
        $user = User::factory()->studentOf($classroom)->create();
        PushSubscription::factory()->for($user)->create(['endpoint' => "https://push.example/{$device}"]);

        return $user;
    }

    /**
     * Roda o agendamento como em produção (a cada 15 minutos, com --due) ao
     * longo de um dia UTC e devolve o horário UTC em que cada um recebeu.
     *
     * @return array{brazil: list<string>, lisbon: list<string>}
     */
    private function runHourly(string $day, string $command, array $arguments = []): array
    {
        $received = ['brazil' => [], 'lisbon' => []];

        foreach (range(0, 24 * 4 - 1) as $quarter) {
            $at = CarbonImmutable::parse($day, 'UTC')->addMinutes(15 * $quarter);
            $this->travelTo($at);
            $before = [$this->push->sentTo($this->brazil)->count(), $this->push->sentTo($this->lisbon)->count()];

            $this->artisan($command, [...$arguments, '--due' => true])->assertSuccessful();

            if ($this->push->sentTo($this->brazil)->count() > $before[0]) {
                $received['brazil'][] = $at->format('H:i');
            }
            if ($this->push->sentTo($this->lisbon)->count() > $before[1]) {
                $received['lisbon'][] = $at->format('H:i');
            }
        }

        return $received;
    }

    public function test_reading_reminders_arrive_at_each_church_local_hour(): void
    {
        // 9h em São Paulo = 12h UTC (como antes); 9h em Lisboa = 8h UTC.
        $this->assertSame(['brazil' => ['12:00'], 'lisbon' => ['08:00']], $this->runHourly('2026-09-23', 'ebd:remind-readings', ['slot' => 'morning']));

        // 20h: 23h UTC em São Paulo, 19h UTC em Lisboa.
        $this->assertSame(['brazil' => ['23:00'], 'lisbon' => ['19:00']], $this->runHourly('2026-09-23', 'ebd:remind-readings', ['slot' => 'evening']));
    }

    public function test_the_eve_reminder_arrives_at_8h_local_on_saturday(): void
    {
        $this->assertSame(['brazil' => ['11:00'], 'lisbon' => ['07:00']], $this->runHourly('2026-09-26', 'ebd:remind-lesson'));
        $this->assertSame('Amanhã tem EBD!', $this->push->sentTo($this->lisbon)->first()->title);
    }

    public function test_half_hour_timezones_get_the_reminder_once_at_their_local_hour(): void
    {
        $india = Church::factory()->create(['timezone' => 'Asia/Kolkata']); // UTC+5:30

        $due = [];
        foreach (range(0, 24 * 4 - 1) as $quarter) {
            $this->travelTo(CarbonImmutable::parse('2026-09-23', 'UTC')->addMinutes(15 * $quarter));
            if (Church::atLocalHour(9)->contains($india)) {
                $due[] = now('Asia/Kolkata')->format('H:i');
            }
        }

        $this->assertSame(['09:00'], $due);
    }

    public function test_without_due_the_command_still_reminds_every_church(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-23 15:00', 'UTC'));

        $this->artisan('ebd:remind-readings', ['slot' => 'morning'])->assertSuccessful();

        $this->assertSame(1, $this->push->sentTo($this->brazil)->count());
        $this->assertSame(1, $this->push->sentTo($this->lisbon)->count());
    }

    public function test_today_rolls_over_at_the_church_midnight(): void
    {
        // Sábado 23h30 UTC: domingo 0h30 em Lisboa, sábado 20h30 em São Paulo.
        $this->travelTo(CarbonImmutable::parse('2026-09-26 23:30', 'UTC'));

        $this->actingAs($this->lisbon)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('today.date', '2026-09-27')
            ->where('today.weekday', 7));

        $this->actingAs($this->brazil)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('today.date', '2026-09-26')
            ->where('today.weekday', 6));

        // Sem igreja em contexto (console, visitante): o fuso da configuração.
        $this->assertSame('2026-09-26', ChurchCalendar::todayString());
    }

    public function test_simons_church_keeps_the_reading_week_boundary_at_sao_paulo_midnight(): void
    {
        $lesson = Lesson::query()->whereHas('classroom', fn ($q) => $q->where('slug', 'jovens-sp'))->firstOrFail();

        // Domingo 27/09, 23h59 em São Paulo (segunda 02h59 UTC): ainda é domingo, dia da aula.
        $this->travelTo(CarbonImmutable::parse('2026-09-28 02:59', 'UTC'));
        $this->actingAs($this->brazil)->get('/')->assertInertia(fn (Assert $page) => $page->where('today.date', '2026-09-27'));
        $this->assertSame(7, ChurchCalendar::readingWeekday($lesson->meetings()->first()->held_on));

        // Meia-noite em São Paulo: segunda, fora da semana daquela aula.
        $this->travelTo(CarbonImmutable::parse('2026-09-28 03:00', 'UTC'));
        $this->actingAs($this->brazil)->get('/')->assertInertia(fn (Assert $page) => $page->where('today.date', '2026-09-28'));
        $this->assertNull(ChurchCalendar::readingWeekday($lesson->meetings()->first()->held_on));
    }

    public function test_admin_pages_of_a_classroom_use_its_church_timezone(): void
    {
        $admin = User::factory()->create();
        Church::query()->where('timezone', 'Europe/Lisbon')->firstOrFail()->admins()->attach($admin);

        // Domingo 23h30 UTC: já é segunda (0h30) em Lisboa, ainda domingo em São Paulo.
        $this->travelTo(CarbonImmutable::parse('2026-09-27 23:30', 'UTC'));

        $this->actingAs($admin)->get('/admin/classes/jovens-lx/domingos')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('nextSunday', '2026-10-04'));

        $owner = User::factory()->admin()->create();
        $this->actingAs($owner)->get('/admin/classes/jovens-sp/domingos')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('nextSunday', '2026-09-27'));
    }
}
