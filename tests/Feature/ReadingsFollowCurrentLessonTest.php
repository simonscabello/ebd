<?php

namespace Tests\Feature;

use App\Actions\Meetings\CancelMeeting;
use App\Actions\Meetings\FinishMeeting;
use App\Actions\Notifications\SendReadingReminders;
use App\Enums\ReminderSlot;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\PushSubscription;
use App\Models\Series;
use App\Models\User;
use App\Support\Push\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePushSender;
use Tests\TestCase;

/**
 * As leituras da semana seguem a lição atual da classe, de ponta a ponta:
 * encerrar o encontro com a lição continuando repete as leituras dela,
 * encerrar com a lição terminada passa para as da próxima, e um domingo
 * "sem EBD" empurra a mesma lição para o encontro seguinte.
 */
class ReadingsFollowCurrentLessonTest extends TestCase
{
    use RefreshDatabase;

    private FakePushSender $push;

    private Classroom $classroom;

    private User $ana;

    private Lesson $first;

    private ClassMeeting $sunday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->push = new FakePushSender;
        $this->app->instance(PushSender::class, $this->push);

        config(['ebd.timezone' => 'Europe/Madrid']);
        // Domingo, 27/09/2026, depois da aula.
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 27)->setTime(12, 0));

        $this->classroom = Classroom::factory()->create(['slug' => 'adultos']);
        $series = Series::factory()->for($this->classroom)->create();
        $this->first = Lesson::factory()->forSeries($series)->number(1)->published()->create(['slug' => 'primeira']);
        $second = Lesson::factory()->forSeries($series)->number(2)->published()->create(['slug' => 'segunda']);

        foreach ([[$this->first, 'A'], [$second, 'B']] as [$lesson, $prefix]) {
            foreach (range(1, 6) as $weekday) {
                LessonReading::factory()->for($lesson)->create(['weekday' => $weekday, 'reference' => "{$prefix}{$weekday}"]);
            }
        }

        $this->classroom->forceFill(['current_series_id' => $series->id, 'current_lesson_id' => $this->first->id])->save();
        $this->sunday = ClassMeeting::factory()->forLesson($this->first)->on('2026-09-27')->create();

        $this->ana = User::factory()->studentOf($this->classroom)->create(['name' => 'Ana']);
        PushSubscription::factory()->for($this->ana)->create(['endpoint' => 'https://fcm.googleapis.com/fcm/send/ana']);

        // Ana leu a segunda-feira da 1ª semana (21/09).
        DB::table('reading_checkins')->insert([
            'user_id' => $this->ana->id, 'lesson_id' => $this->first->id, 'weekday' => 1,
            'read_on' => '2026-09-21', 'created_at' => now(),
        ]);
    }

    private function travelToDay(int $month, int $day, int $hour = 10): void
    {
        $this->travelTo(now('Europe/Madrid')->setDate(2026, $month, $day)->setTime($hour, 0));
    }

    private function morningReminder(): string
    {
        $this->push->sent = [];
        app(SendReadingReminders::class)->handle(ReminderSlot::Morning);

        return $this->push->sentTo($this->ana)->sole()->title;
    }

    public function test_lesson_continues_next_week_repeats_the_same_readings_from_monday(): void
    {
        app(FinishMeeting::class)->handle($this->sunday, continues: true, notes: null);

        $this->travelToDay(9, 28);

        $this->actingAs($this->ana)->get('/minha-semana')
            ->assertInertia(fn (Assert $page) => $page
                ->where('week.lesson.slug', 'primeira')
                ->where('week.meeting.held_on', '2026-10-04')
                ->where('week.meeting.index', 2)
                ->where('week.days.0.is_today', true)
                ->where('week.days.0.readings.0.reference', 'A1')
                // A marcação da semana passada não vale nesta.
                ->where('week.days.0.done', false)
                ->where('week.progress.days_done', 0));
        $this->assertSame('Leitura de hoje: A1', $this->morningReminder());

        // Marca de novo; o histórico da 1ª semana fica.
        $this->actingAs($this->ana)->post('/licoes/primeira/leituras', ['weekday' => 1])->assertRedirect();
        $this->assertSame(2, DB::table('reading_checkins')->where('lesson_id', $this->first->id)->where('weekday', 1)->count());
        $this->actingAs($this->ana)->get('/minha-semana')
            ->assertInertia(fn (Assert $page) => $page->where('week.days.0.done', true));
    }

    public function test_lesson_ends_next_week_starts_the_readings_of_the_new_current_lesson(): void
    {
        app(FinishMeeting::class)->handle($this->sunday, continues: false, notes: null);

        $this->travelToDay(9, 28);

        $this->actingAs($this->ana)->get('/minha-semana')
            ->assertInertia(fn (Assert $page) => $page
                ->where('week.lesson.slug', 'segunda')
                ->where('week.meeting.held_on', '2026-10-04')
                ->where('week.meeting.index', 1)
                ->where('week.days.0.is_today', true)
                ->where('week.days.0.readings.0.reference', 'B1')
                ->where('week.days.0.done', false));
        $this->assertSame('Leitura de hoje: B1', $this->morningReminder());

        // O que ela leu da lição anterior continua no histórico.
        $this->assertSame(1, DB::table('reading_checkins')->where('lesson_id', $this->first->id)->count());
    }

    public function test_cancelled_meeting_keeps_reading_the_same_lesson_and_repeats_it_the_week_after(): void
    {
        app(FinishMeeting::class)->handle($this->sunday, continues: true, notes: null);
        $next = ClassMeeting::query()->whereDate('held_on', '2026-10-04')->sole();

        $this->travelToDay(9, 28);
        $this->actingAs($this->ana)->post('/licoes/primeira/leituras', ['weekday' => 1])->assertRedirect();

        // Na quarta, o professor avisa que não vai ter EBD no domingo 04/10.
        $this->travelToDay(9, 30);
        app(CancelMeeting::class)->handle($next, 'Congresso');

        $this->assertSame($this->first->id, $this->classroom->refresh()->current_lesson_id);
        $this->assertSame($this->first->id, ClassMeeting::query()->whereDate('held_on', '2026-10-11')->value('lesson_id'));

        // A semana do domingo cancelado segue com as leituras da lição atual até o fim.
        $this->travelToDay(10, 1);
        $this->assertSame('Leitura de hoje: A4', $this->morningReminder());
        $this->actingAs($this->ana)->get('/minha-semana')
            ->assertInertia(fn (Assert $page) => $page
                ->where('week.lesson.slug', 'primeira')
                ->where('week.meeting.held_on', '2026-10-11')
                ->where('week.days.0.date', '2026-09-28')
                ->where('week.days.0.done', true)
                ->where('week.days.3.is_today', true)
                ->where('week.days.3.readings.0.reference', 'A4'));
        $this->actingAs($this->ana)->get('/licoes/primeira')
            ->assertInertia(fn (Assert $page) => $page->where('lesson.readings.3.is_today', true));

        // A semana seguinte repete as mesmas leituras, do primeiro dia, sem nada marcado.
        $this->travelToDay(10, 5);
        $this->assertSame('Leitura de hoje: A1', $this->morningReminder());
        $this->actingAs($this->ana)->get('/minha-semana')
            ->assertInertia(fn (Assert $page) => $page
                ->where('week.lesson.slug', 'primeira')
                ->where('week.days.0.date', '2026-10-05')
                ->where('week.days.0.is_today', true)
                ->where('week.days.0.readings.0.reference', 'A1')
                ->where('week.days.0.done', false)
                ->where('week.progress.days_done', 0));

        $this->actingAs($this->ana)->post('/licoes/primeira/leituras', ['weekday' => 1])->assertRedirect();
        $this->assertSame(3, DB::table('reading_checkins')->where('lesson_id', $this->first->id)->where('weekday', 1)->count());
    }
}
