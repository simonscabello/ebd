<?php

namespace Tests\Feature;

use App\Actions\Notifications\SendReadingReminders;
use App\Enums\MeetingStatus;
use App\Enums\ReminderSlot;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Push\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePushSender;
use Tests\TestCase;

/**
 * D2: numa lição que continua no domingo seguinte, cada semana de leitura é
 * uma semana própria. A marcação da 1ª semana não aparece como feita na 2ª, a
 * leitura de hoje é gravada de novo (e conta na sequência de dias), e o
 * lembrete da noite só olha a semana de hoje.
 */
class ContinuingLessonReadingTest extends TestCase
{
    use RefreshDatabase;

    private FakePushSender $push;

    private Classroom $classroom;

    private User $ana;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->push = new FakePushSender;
        $this->app->instance(PushSender::class, $this->push);

        config(['ebd.timezone' => 'Europe/Madrid']);
        // Segunda-feira, 21/09/2026: 1ª semana da lição (domingos 27/09 e 04/10).
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 21)->setTime(10, 0));

        $this->classroom = Classroom::factory()->create(['slug' => 'adultos']);
        $this->ana = User::factory()->studentOf($this->classroom)->create(['name' => 'Ana']);
        PushSubscription::factory()->for($this->ana)->create(['endpoint' => 'https://fcm.googleapis.com/fcm/send/ana']);

        $this->lesson = Lesson::factory()->for($this->classroom)->published()->on('2026-09-27')->create(['slug' => 'continua']);
        ClassMeeting::factory()->forLesson($this->lesson)->on('2026-10-04')->create(['status' => MeetingStatus::Planned]);

        foreach (range(1, 6) as $weekday) {
            LessonReading::factory()->for($this->lesson)->create(['weekday' => $weekday, 'reference' => "Leitura {$weekday}"]);
        }
    }

    /**
     * @return list<string>
     */
    private function mondayReadOn(): array
    {
        return DB::table('reading_checkins')->where('user_id', $this->ana->id)->where('weekday', 1)->orderBy('read_on')
            ->pluck('read_on')->map(fn ($d) => substr((string) $d, 0, 10))->all();
    }

    public function test_each_reading_week_of_a_continuing_lesson_has_its_own_checkins(): void
    {
        $this->actingAs($this->ana)->post('/licoes/continua/leituras', ['weekday' => 1])->assertRedirect();

        // Segunda-feira da 2ª semana: a leitura de segunda ainda não foi feita nesta semana.
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 28)->setTime(10, 0));

        $this->actingAs($this->ana)->get('/minha-semana')
            ->assertInertia(fn (Assert $page) => $page
                ->where('week.lesson.slug', 'continua')
                ->where('week.meeting.index', 2)
                ->where('week.days.0.is_today', true)
                ->where('week.days.0.done', false)
                ->where('week.progress.days_done', 0)
                ->where('week.streak.today_done', false));
        $this->actingAs($this->ana)->get('/licoes/continua')
            ->assertInertia(fn (Assert $page) => $page->where('study.checked_weekdays', []));

        // Marcar grava a leitura de hoje e conta na sequência de dias.
        $this->actingAs($this->ana)->post('/licoes/continua/leituras', ['weekday' => 1])->assertRedirect();
        $this->actingAs($this->ana)->post('/licoes/continua/leituras', ['weekday' => 1])->assertRedirect();
        $this->assertSame(['2026-09-21', '2026-09-28'], $this->mondayReadOn());

        $this->actingAs($this->ana)->get('/minha-semana')
            ->assertInertia(fn (Assert $page) => $page
                ->where('week.days.0.done', true)
                ->where('week.progress.days_done', 1)
                ->where('week.streak.today_done', true));

        // Desmarcar mexe só na semana de hoje.
        $this->actingAs($this->ana)->delete('/licoes/continua/leituras', ['weekday' => 1])->assertRedirect();
        $this->assertSame(['2026-09-21'], $this->mondayReadOn());
    }

    public function test_evening_reminder_only_looks_at_this_reading_week(): void
    {
        $this->actingAs($this->ana)->post('/licoes/continua/leituras', ['weekday' => 1]);

        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 28)->setTime(20, 0));
        app(SendReadingReminders::class)->handle(ReminderSlot::Evening);
        $this->assertSame('Ainda dá tempo: Leitura 1', $this->push->sentTo($this->ana)->sole()->title);

        $this->actingAs($this->ana)->post('/licoes/continua/leituras', ['weekday' => 1]);
        $this->push->sent = [];
        app(SendReadingReminders::class)->handle(ReminderSlot::Evening);
        $this->assertSame('Você já leu hoje. Que tal relembrar o texto antes de dormir?', $this->push->sentTo($this->ana)->sole()->body);
    }

    public function test_full_week_badge_needs_six_days_in_the_same_reading_week(): void
    {
        foreach ([1, 2, 3] as $weekday) {
            $this->actingAs($this->ana)->post('/licoes/continua/leituras', ['weekday' => $weekday]);
        }

        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 28)->setTime(10, 0));

        foreach ([4, 5, 6] as $weekday) {
            $this->actingAs($this->ana)->post('/licoes/continua/leituras', ['weekday' => $weekday]);
        }

        $this->assertDatabaseMissing('user_badges', ['user_id' => $this->ana->id, 'badge' => 'first_full_week']);

        foreach ([1, 2, 3] as $weekday) {
            $this->actingAs($this->ana)->post('/licoes/continua/leituras', ['weekday' => $weekday]);
        }

        $this->assertDatabaseHas('user_badges', ['user_id' => $this->ana->id, 'badge' => 'first_full_week']);
    }

    public function test_single_meeting_lesson_keeps_counting_early_and_late_checkins(): void
    {
        $single = Lesson::factory()->for($this->classroom)->published()->on('2026-10-11')->create(['slug' => 'uma-semana']);

        // Adiantou na semana anterior e pôs em dia depois da aula: vale tudo, como sempre.
        $this->actingAs($this->ana)->post('/licoes/uma-semana/leituras', ['weekday' => 2]);
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 10, 13)->setTime(10, 0));
        $this->actingAs($this->ana)->post('/licoes/uma-semana/leituras', ['weekday' => 2]);
        $this->actingAs($this->ana)->post('/licoes/uma-semana/leituras', ['weekday' => 3]);

        $this->assertSame(2, DB::table('reading_checkins')->where('lesson_id', $single->id)->count());
        $this->actingAs($this->ana)->get('/licoes/uma-semana')
            ->assertInertia(fn (Assert $page) => $page->where('study.checked_weekdays', [2, 3]));
    }
}
