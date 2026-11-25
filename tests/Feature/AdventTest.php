<?php

namespace Tests\Feature;

use App\Actions\Notifications\SendAdventReminders;
use App\Enums\Badge;
use App\Models\BibleVerse;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Push\PushSender;
use App\Support\StudyStreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePushSender;
use Tests\TestCase;

/**
 * Natal em Lucas: um capítulo por dia de 1º a 24 de dezembro.
 */
class AdventTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'Europe/Madrid']);
        $this->goTo(12, 3);

        $this->classroom = Classroom::factory()->create();
        $this->student = User::factory()->studentOf($this->classroom)->create();
    }

    private function goTo(int $month, int $day, int $hour = 10): void
    {
        $this->travelTo(now('Europe/Madrid')->setDate(2026, $month, $day)->setTime($hour, 0));
    }

    /**
     * @return list<int>
     */
    private function days(User $user): array
    {
        return DB::table('advent_checkins')->where('user_id', $user->id)->orderBy('day')->pluck('day')->map(fn ($d) => (int) $d)->all();
    }

    public function test_past_and_today_can_be_marked_but_not_the_future(): void
    {
        $this->actingAs($this->student)->post('/natal/1')->assertRedirect();
        $this->actingAs($this->student)->post('/natal/3')->assertRedirect();
        $this->actingAs($this->student)->post('/natal/3')->assertRedirect();
        $this->actingAs($this->student)->post('/natal/4')->assertStatus(422);

        $this->assertSame([1, 3], $this->days($this->student));

        $this->actingAs($this->student)->get('/natal')
            ->assertInertia(fn (Assert $page) => $page
                ->component('advent')
                ->where('today', 3)
                ->where('days.0.read', true)
                ->where('days.1.read', false)
                ->where('days.2.locked', false)
                ->where('days.3.locked', true)
                ->has('days', 24));

        $this->actingAs($this->student)->delete('/natal/1')->assertRedirect();
        $this->assertSame([3], $this->days($this->student));
    }

    public function test_it_counts_in_the_streak_and_shows_on_home(): void
    {
        $this->actingAs($this->student)->post('/natal/3');

        $this->assertTrue(app(StudyStreak::class)->for($this->student)['today_done']);

        $this->actingAs($this->student)->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->where('advent.state', 'open')
                ->where('advent.reference', 'Lucas 3')
                ->where('advent.today_done', true)
                ->where('advent.read', 1));
    }

    public function test_outside_the_season_there_is_nothing(): void
    {
        $this->goTo(11, 20);

        $this->actingAs($this->student)->get('/')->assertInertia(fn (Assert $page) => $page->where('advent', null));
        $this->actingAs($this->student)->get('/natal')->assertNotFound();
        $this->actingAs($this->student)->post('/natal/1')->assertStatus(422);

        // Na última semana de novembro a home já anuncia; marcar ainda não dá.
        $this->goTo(11, 27);

        $this->actingAs($this->student)->get('/')->assertInertia(fn (Assert $page) => $page->where('advent.state', 'upcoming'));
        $this->actingAs($this->student)->post('/natal/1')->assertStatus(422);

        // Depois do dia 24, até 31/12, dá para pôr em dia.
        $this->goTo(12, 28);

        $this->actingAs($this->student)->post('/natal/24')->assertRedirect();
        $this->assertSame([24], $this->days($this->student));
    }

    public function test_the_text_only_goes_to_churches_with_a_license(): void
    {
        BibleVerse::query()->create(['book' => 42, 'chapter' => 3, 'verse' => 1, 'text' => 'No décimo quinto ano']);

        $this->actingAs($this->student)->get('/natal?dia=3')
            ->assertInertia(fn (Assert $page) => $page
                ->where('reading.reference', 'Lucas 3')
                ->where('reading.passage.verses.0.text', 'No décimo quinto ano'));

        // Capítulo ainda travado não abre.
        $this->actingAs($this->student)->get('/natal?dia=9')
            ->assertInertia(fn (Assert $page) => $page->where('reading', null));

        $this->classroom->church->update(['shows_bible_text' => false]);

        $this->actingAs($this->student)->get('/natal?dia=3')
            ->assertInertia(fn (Assert $page) => $page
                ->where('reading.reference', 'Lucas 3')
                ->where('reading.passage', null));
    }

    public function test_readers_are_counted_per_church(): void
    {
        $classmate = User::factory()->studentOf($this->classroom)->create();
        $elsewhere = User::factory()->studentOf(Classroom::factory()->for(Church::factory())->create())->create();

        foreach ([$this->student, $classmate, $elsewhere] as $user) {
            $this->actingAs($user)->post('/natal/2')->assertRedirect();
        }

        $this->actingAs($this->student)->get('/natal')
            ->assertInertia(fn (Assert $page) => $page->where('days.1.readers', 2));
        $this->actingAs($elsewhere)->get('/natal')
            ->assertInertia(fn (Assert $page) => $page->where('days.1.readers', 1));
    }

    public function test_reading_all_24_chapters_earns_the_badge(): void
    {
        $this->goTo(12, 24);

        foreach (range(1, 23) as $day) {
            $this->actingAs($this->student)->post("/natal/{$day}");
        }

        $this->assertFalse($this->student->badges()->where('badge', Badge::LukeAdvent)->exists());

        $this->actingAs($this->student)->post('/natal/24');

        $this->assertTrue($this->student->badges()->where('badge', Badge::LukeAdvent)->exists());
    }

    public function test_the_reminder_goes_once_a_day_to_who_has_not_read(): void
    {
        $push = new FakePushSender;
        $this->app->instance(PushSender::class, $push);

        $reader = User::factory()->studentOf($this->classroom)->create();
        // Em duas turmas da mesma igreja: um aviso só.
        $this->student->classrooms()->attach(Classroom::factory()->for($this->classroom->church)->create(), ['role' => 'student']);

        foreach ([$this->student, $reader] as $user) {
            PushSubscription::factory()->for($user)->create();
        }

        $this->actingAs($reader)->post('/natal/3');

        $this->assertSame(1, app(SendAdventReminders::class)->handle());
        $this->assertSame('Natal em Lucas · dia 3', $push->sentTo($this->student)->sole()->title);
        $this->assertStringEndsWith('/natal?dia=3', $push->sentTo($this->student)->sole()->url);
        $this->assertCount(0, $push->sentTo($reader));

        // Dia 25 em diante não há capítulo do dia.
        $this->goTo(12, 25);

        $this->assertSame(0, app(SendAdventReminders::class)->handle());
    }
}
