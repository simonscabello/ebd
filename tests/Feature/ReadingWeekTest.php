<?php

namespace Tests\Feature;

use App\Actions\Notifications\SendReadingReminders;
use App\Enums\Badge;
use App\Enums\ReminderSlot;
use App\Enums\Weekday;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\ChurchCalendar;
use App\Support\Push\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePushSender;
use Tests\TestCase;

/**
 * A semana de leitura vai dos seis dias antes do encontro até o dia do
 * encontro. Para quem se reúne no domingo, continua sendo segunda a domingo.
 */
class ReadingWeekTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $student;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'America/Sao_Paulo']);
        // Sexta-feira, 02/10/2026, 9h.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 10, 2)->setTime(9, 0));

        $this->classroom = Classroom::factory()->create(['slug' => 'quarta', 'meeting_weekday' => Weekday::Wednesday]);
        $this->student = User::factory()->studentOf($this->classroom)->create();
        $this->lesson = Lesson::factory()->for($this->classroom)->published()->on('2026-10-07')->create(['slug' => 'licao-de-quarta']);
    }

    public function test_reading_week_ends_on_the_meeting_day(): void
    {
        $this->assertSame('2026-10-01', ChurchCalendar::readingWeekStart(now()->parse('2026-10-07'))->toDateString());
        $this->assertSame('2026-09-28', ChurchCalendar::readingWeekStart(now()->parse('2026-10-04'))->toDateString());
        $this->assertSame(5, ChurchCalendar::readingWeekday(now()->parse('2026-10-07'), now()->parse('2026-10-02')));
        $this->assertNull(ChurchCalendar::readingWeekday(now()->parse('2026-10-07'), now()->parse('2026-09-30')));
    }

    public function test_wednesday_class_week_runs_thursday_to_wednesday(): void
    {
        foreach ([4, 5, 6, 7, 1, 2, 3] as $weekday) {
            LessonReading::factory()->for($this->lesson)->create(['weekday' => $weekday, 'reference' => "Leitura {$weekday}"]);
        }

        $this->actingAs($this->student)->get('/minha-semana')
            ->assertInertia(fn (Assert $page) => $page
                ->where('week.days.0.date', '2026-10-01')
                ->where('week.days.0.weekday', 4)
                ->where('week.days.1.is_today', true)
                ->where('week.days.1.weekday', 5)
                ->where('week.days.6.date', '2026-10-07')
                ->where('week.days.6.is_meeting_day', true)
                ->where('week.days.5.is_meeting_day', false)
                ->where('week.progress.days_total', 6));

        $this->actingAs($this->student)->post('/licoes/licao-de-quarta/leituras', ['weekday' => 5])->assertRedirect();
        $this->assertSame([5], DB::table('reading_checkins')->where('user_id', $this->student->id)->pluck('weekday')->map(fn ($d) => (int) $d)->all());
    }

    public function test_first_full_week_badge_counts_the_six_days_before_the_meeting(): void
    {
        foreach ([4, 5, 6, 7, 1, 2] as $weekday) {
            DB::table('reading_checkins')->insert([
                'user_id' => $this->student->id, 'lesson_id' => $this->lesson->id, 'weekday' => $weekday,
                'read_on' => '2026-10-02', 'created_at' => now(),
            ]);
        }

        $this->actingAs($this->student)->get('/meu-progresso')
            ->assertInertia(fn (Assert $page) => $page->where('progress.available_badges.0.badge', Badge::FirstFullWeek->value)
                ->where('progress.available_badges.0.progress.current', 6));
    }

    public function test_no_reread_reminder_on_the_meeting_day_of_a_wednesday_class(): void
    {
        $push = new FakePushSender;
        $this->app->instance(PushSender::class, $push);
        PushSubscription::factory()->for($this->student)->create();
        $this->lesson->update(['bible_reference' => 'Lucas 5.1-11']);

        // Quarta-feira, dia da aula: sem "releia o texto base".
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 10, 7)->setTime(9, 0));
        app(SendReadingReminders::class)->handle(ReminderSlot::Morning);
        $this->assertCount(0, $push->sentTo($this->student));

        // Domingo, no meio da semana de leitura: lembra o texto base.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 10, 4)->setTime(9, 0));
        app(SendReadingReminders::class)->handle(ReminderSlot::Morning);
        $this->assertSame('Releia o texto base da lição.', $push->sentTo($this->student)->first()->body);
    }
}
