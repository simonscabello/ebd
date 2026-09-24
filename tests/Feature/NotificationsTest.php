<?php

namespace Tests\Feature;

use App\Actions\Notifications\SendLessonReminder;
use App\Actions\Notifications\SendReadingReminders;
use App\Enums\ReminderSlot;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\PushSubscription;
use App\Models\ReadingCheckin;
use App\Models\User;
use App\Support\Push\PushMessage;
use App\Support\Push\PushSender;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Support\SessionKey;
use Tests\Support\FakePushSender;
use Tests\TestCase;

/**
 * Lembretes push: leitura do dia (9h/20h), véspera da EBD e lição publicada.
 */
class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private FakePushSender $push;

    private Classroom $classroom;

    private User $teacher;

    private User $ana;

    private User $bia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->push = new FakePushSender;
        $this->app->instance(PushSender::class, $this->push);

        config(['ebd.timezone' => 'Europe/Madrid']);
        // Quarta-feira, 23/09/2026, 9h em Madri.
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 23)->setTime(9, 0));

        $this->classroom = Classroom::factory()->create(['name' => 'Jovens', 'slug' => 'jovens']);
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
        $this->ana = User::factory()->studentOf($this->classroom)->create(['name' => 'Ana']);
        $this->bia = User::factory()->studentOf($this->classroom)->create(['name' => 'Bia']);

        $this->subscribe($this->teacher, 'prof');
        $this->subscribe($this->ana, 'ana-celular');
        $this->subscribe($this->ana, 'ana-tablet');
        $this->subscribe($this->bia, 'bia');
    }

    private function subscribe(User $user, string $device): void
    {
        PushSubscription::factory()->for($user)->create(['endpoint' => "https://push.example/{$device}"]);
    }

    private function lessonOfTheWeek(): Lesson
    {
        return Lesson::factory()->for($this->classroom)->published()->on('2026-09-27')->create([
            'title' => 'A Santidade de Deus',
            'number' => 3,
            'bible_reference' => 'Isaías 6.1-8',
            'summary' => 'Um encontro com o Deus três vezes santo.',
        ]);
    }

    public function test_morning_reminder_goes_to_every_subscribed_member_with_todays_reading(): void
    {
        $lesson = $this->lessonOfTheWeek();
        LessonReading::factory()->for($lesson)->create(['weekday' => 3, 'reference' => 'Salmo 99', 'notes' => 'O Senhor reina.']);
        LessonReading::factory()->for($lesson)->create(['weekday' => 4, 'reference' => 'Êxodo 3.1-6']);

        $sent = app(SendReadingReminders::class)->handle(ReminderSlot::Morning);

        // Ana tem dois aparelhos.
        $this->assertSame(4, $sent);
        $message = $this->push->sentTo($this->ana)->first();
        $this->assertSame('Leitura de hoje: Salmo 99', $message->title);
        $this->assertSame('O Senhor reina.', $message->body);
        $this->assertSame(route('my-week', ['classe' => 'jovens']), $message->url);
        $this->assertSame("reading:{$lesson->id}:3", $message->tag);
        $this->assertCount(1, $this->push->sentTo($this->teacher));
    }

    public function test_evening_reminder_skips_who_already_read_today(): void
    {
        $lesson = $this->lessonOfTheWeek();
        LessonReading::factory()->for($lesson)->create(['weekday' => 3, 'reference' => 'Salmo 99']);
        ReadingCheckin::query()->forceCreate(['user_id' => $this->ana->id, 'lesson_id' => $lesson->id, 'weekday' => 3, 'read_on' => '2026-09-23']);

        $this->artisan('ebd:remind-readings', ['slot' => 'evening'])->assertSuccessful();

        $this->assertCount(0, $this->push->sentTo($this->ana));
        $this->assertSame('Ainda dá tempo: Salmo 99', $this->push->sentTo($this->bia)->first()->title);
        $this->assertCount(1, $this->push->sentTo($this->teacher));
    }

    public function test_without_a_reading_plan_weekdays_remind_the_base_text(): void
    {
        $this->lessonOfTheWeek();

        app(SendReadingReminders::class)->handle(ReminderSlot::Morning);

        $message = $this->push->sentTo($this->bia)->first();
        $this->assertSame('Leitura de hoje: Isaías 6.1-8', $message->title);
        $this->assertSame('Releia o texto base da lição.', $message->body);
    }

    public function test_nothing_is_sent_without_a_lesson_of_the_week_or_on_a_day_without_reading(): void
    {
        app(SendReadingReminders::class)->handle(ReminderSlot::Morning);
        $this->assertSame(0, $this->push->count());

        // Lição só em rascunho: o professor a enxerga, os alunos não.
        $draft = Lesson::factory()->for($this->classroom)->on('2026-09-27')->create();
        app(SendReadingReminders::class)->handle(ReminderSlot::Morning);
        $this->assertSame(0, $this->push->count());

        // Plano com leituras, mas nenhuma na quarta.
        ClassMeeting::query()->where('lesson_id', $draft->id)->delete();
        $draft->forceDelete();
        $lesson = $this->lessonOfTheWeek();
        LessonReading::factory()->for($lesson)->create(['weekday' => 4, 'reference' => 'Êxodo 3.1-6']);
        app(SendReadingReminders::class)->handle(ReminderSlot::Morning);
        $this->assertSame(0, $this->push->count());
    }

    public function test_the_eve_of_class_reminds_the_lesson_only_when_there_is_a_meeting_tomorrow(): void
    {
        $lesson = $this->lessonOfTheWeek();

        // Quarta: domingo não é amanhã.
        app(SendLessonReminder::class)->handle();
        $this->assertSame(0, $this->push->count());

        // Sábado 26/09, 8h.
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 26)->setTime(8, 0));
        $this->artisan('ebd:remind-lesson')->assertSuccessful();

        $this->assertSame(4, $this->push->count());
        $message = $this->push->sentTo($this->bia)->first();
        $this->assertSame('Amanhã tem EBD!', $message->title);
        $this->assertSame('Lição 3 — A Santidade de Deus · Isaías 6.1-8', $message->body);
        $this->assertSame(route('lessons.show', $lesson->slug), $message->url);
    }

    public function test_a_cancelled_sunday_sends_no_eve_reminder(): void
    {
        $lesson = $this->lessonOfTheWeek();
        ClassMeeting::query()->where('lesson_id', $lesson->id)->update(['status' => 'cancelled']);

        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 26)->setTime(8, 0));
        app(SendLessonReminder::class)->handle();

        $this->assertSame(0, $this->push->count());
    }

    public function test_publishing_a_lesson_notifies_everyone_in_the_classroom(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->create(['title' => 'Nova', 'number' => 4, 'summary' => 'Resumo da lição nova.']);
        User::factory()->studentOf(Classroom::factory()->create())->create(); // outra classe, sem aparelho

        $this->actingAs($this->teacher)
            ->post("/admin/licoes/{$lesson->id}/status", ['status' => 'published'])
            ->assertRedirect();

        // Ana tem dois aparelhos; o professor que publicou também recebe.
        $this->assertSame(4, $this->push->count());
        $this->assertCount(1, $this->push->sentTo($this->teacher));
        $message = $this->push->sentTo($this->ana)->first();
        $this->assertSame('Nova lição: Lição 4 — Nova', $message->title);
        $this->assertSame('Resumo da lição nova.', $message->body);
        $this->assertSame(route('lessons.show', $lesson->slug), $message->url);

        // Despublicar e publicar de novo avisa outra vez: é uma nova publicação.
        $this->actingAs($this->teacher)->post("/admin/licoes/{$lesson->id}/status", ['status' => 'draft']);
        $this->actingAs($this->teacher)->post("/admin/licoes/{$lesson->id}/status", ['status' => 'published']);
        $this->assertSame(8, $this->push->count());
    }

    public function test_a_push_failure_never_blocks_publishing_and_is_told_to_the_teacher(): void
    {
        $this->app->instance(PushSender::class, new class implements PushSender
        {
            public function send(Collection $subscriptions, PushMessage $message): int
            {
                throw new \RuntimeException('serviço de push fora do ar');
            }
        });
        $lesson = Lesson::factory()->for($this->classroom)->create();

        $this->actingAs($this->teacher)
            ->post("/admin/licoes/{$lesson->id}/status", ['status' => 'published'])
            ->assertRedirect()
            ->assertSessionHas(SessionKey::FLASH_DATA, fn (array $flash) => str_contains($flash['toast']['message'], 'aviso no celular falhou'));

        $this->assertTrue($lesson->refresh()->status->isVisible());
    }

    public function test_the_publish_toast_tells_how_many_devices_were_notified(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->create();

        $this->actingAs($this->teacher)
            ->post("/admin/licoes/{$lesson->id}/status", ['status' => 'published'])
            ->assertSessionHas(SessionKey::FLASH_DATA, fn (array $flash) => str_contains($flash['toast']['message'], '4 aparelho(s)'));

        PushSubscription::query()->delete();
        $other = Lesson::factory()->for($this->classroom)->create();

        $this->actingAs($this->teacher)
            ->post("/admin/licoes/{$other->id}/status", ['status' => 'published'])
            ->assertSessionHas(SessionKey::FLASH_DATA, fn (array $flash) => str_contains($flash['toast']['message'], 'Ninguém da classe ativou'));
    }

    public function test_reminders_are_scheduled_in_the_church_timezone(): void
    {
        // O agendamento é montado quando o console é carregado (antes do setUp
        // trocar a config), com o fuso do phpunit.xml.
        $events = collect(app(Schedule::class)->events())
            ->mapWithKeys(fn ($event) => [trim(str_replace(['artisan', "'", '"'], '', substr($event->command ?? '', strpos($event->command ?? '', 'artisan')))) => [$event->expression, $event->timezone]]);

        $this->assertSame(['0 9 * * *', 'America/Sao_Paulo'], $events['ebd:remind-readings morning']);
        $this->assertSame(['0 20 * * *', 'America/Sao_Paulo'], $events['ebd:remind-readings evening']);
        $this->assertSame(['0 8 * * 6', 'America/Sao_Paulo'], $events['ebd:remind-lesson']);
    }
}
