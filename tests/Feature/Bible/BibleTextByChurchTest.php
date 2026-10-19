<?php

namespace Tests\Feature\Bible;

use App\Actions\Notifications\SendReadingReminders;
use App\Enums\BibleBook;
use App\Enums\ReminderSlot;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Bible\Reference;
use App\Support\Push\PushSender;
use App\Support\SplashVerse;
use App\Support\WeeklyMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeBible;
use Tests\Support\FakePushSender;
use Tests\TestCase;

/**
 * O texto da NAA só aparece em lição de igreja com licença (a do dono).
 * Nas outras, em todo caminho por onde o texto sai, vai só a referência.
 */
class BibleTextByChurchTest extends TestCase
{
    use RefreshDatabase;

    private Lesson $licensed;

    private Lesson $other;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        FakeBible::seed([BibleBook::Luke->value => [5 => 20]]);
        // Quarta-feira, 23/09/2026: semana de leitura do encontro de domingo, 27.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 9, 23)->setTime(9, 0));

        $owner = Classroom::factory()->create(['slug' => 'jovens']);
        $this->licensed = $this->lessonIn($owner, 'licao-licenciada');

        $classroom = Classroom::factory()->for(Church::factory())->create(['slug' => 'outra']);
        $this->student = User::factory()->studentOf($classroom)->create();
        $this->other = $this->lessonIn($classroom, 'licao-outra');
    }

    private function lessonIn(Classroom $classroom, string $slug): Lesson
    {
        $lesson = Lesson::factory()->for($classroom)->published()->on('2026-09-27')->create([
            'slug' => $slug,
            'bible_reference' => 'Lc 5.12-13',
            'key_verse' => 'Lc 5.13',
            'content' => 'Jesus toca o leproso (Lc 5.12).',
        ]);
        LessonReading::factory()->for($lesson)->create(['weekday' => 3, 'reference' => 'Lc 5.14', 'notes' => null]);

        return $lesson;
    }

    public function test_the_lesson_page_of_another_church_has_only_references(): void
    {
        foreach ([null, $this->student] as $viewer) {
            ($viewer ? $this->actingAs($viewer) : $this)
                ->get('/licoes/licao-outra')
                ->assertInertia(fn (Assert $page) => $page
                    ->where('lesson.key_verse', 'Lc 5.13')
                    ->where('lesson.key_verse_passage', null)
                    ->where('lesson.bible_passage', null)
                    ->where('lesson.readings.0.reference', 'Lc 5.14')
                    ->where('lesson.readings.0.passage', null));
        }
    }

    public function test_the_licensed_lesson_keeps_the_text_even_for_guests(): void
    {
        $this->get('/licoes/licao-licenciada')
            ->assertInertia(fn (Assert $page) => $page
                ->where('lesson.key_verse_passage.verses.0.text', 'Lucas 5.13')
                ->where('lesson.bible_passage.verses.0.text', 'Lucas 5.12')
                ->where('lesson.readings.0.passage.verses.0.text', 'Lucas 5.14'));
    }

    public function test_the_bible_endpoint_follows_the_lesson(): void
    {
        $this->getJson('/biblia?ref=Lc%205.12&licao=licao-outra')
            ->assertOk()
            ->assertJsonPath('passages.0.reference', 'Lc 5.12')
            ->assertJsonPath('passages.0.passage', null);

        $this->getJson('/biblia?ref=Lc%205.12&licao=licao-licenciada')
            ->assertJsonPath('passages.0.passage.verses.0.text', 'Lucas 5.12');

        // Sem a lição, vale a igreja de quem pede.
        $this->actingAs($this->student)->getJson('/biblia?ref=Lc%205.12')
            ->assertJsonPath('passages.0.passage', null);
    }

    public function test_the_week_of_a_student_of_another_church_has_no_text(): void
    {
        $this->actingAs($this->student)->get('/minha-semana')
            ->assertInertia(fn (Assert $page) => $page
                ->where('week.days.2.readings.0.reference', 'Lc 5.14')
                ->where('week.days.2.readings.0.passage', null));
    }

    public function test_reminders_and_share_texts_do_not_carry_the_verse(): void
    {
        $push = new FakePushSender;
        $this->app->instance(PushSender::class, $push);
        PushSubscription::factory()->for($this->student)->create();

        app(SendReadingReminders::class)->handle(ReminderSlot::Morning);

        $this->assertSame('Abra o app e marque quando ler.', $push->sentTo($this->student)->first()->body);

        $weekly = app(WeeklyMessage::class)->for($this->other->meetings()->first());
        $this->assertStringContainsString('Lc 5.13', $weekly);
        $this->assertStringNotContainsString('Lucas 5.13', $weekly);
    }

    public function test_the_ai_context_has_no_verse_text_for_another_church(): void
    {
        config(['services.openai.key' => 'sk-test']);
        $this->other->classroom->church->update(['helper_enabled' => true]);
        Http::fake(['api.openai.com/*' => Http::response([
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Resposta.']]]],
        ])]);

        $this->actingAs($this->student)
            ->postJson('/licoes/licao-outra/duvidas', ['question' => 'Quem era o leproso?'])
            ->assertOk();

        Http::assertSent(fn (Request $request) => str_contains((string) $request['input'], 'Lc 5.12-13')
            && ! str_contains((string) $request['input'], 'Lucas 5.12'));
    }

    public function test_the_splash_has_no_verse_for_a_member_of_another_church(): void
    {
        $shape = [];
        foreach (SplashVerse::REFERENCES as $reference) {
            foreach (Reference::parse($reference)->ranges as $range) {
                $shape[$range->book->value][$range->fromChapter] = 200;
            }
        }
        unset($shape[BibleBook::Luke->value]);
        FakeBible::seed($shape);

        $this->assertSame(SplashVerse::FALLBACK, SplashVerse::pick($this->student)['text']);
        $this->assertNotSame(SplashVerse::FALLBACK, SplashVerse::pick(null)['text']);
    }

    public function test_the_admin_preview_recognizes_the_reference_without_the_text(): void
    {
        $teacher = User::factory()->teacherOf($this->other->classroom)->create();

        $this->actingAs($teacher)
            ->getJson(route('admin.bible.preview', ['reference' => 'Lc 5.12-13']))
            ->assertOk()
            ->assertJsonPath('passage.label', 'Lucas 5.12-13')
            ->assertJsonCount(2, 'passage.verses')
            ->assertJsonPath('passage.verses.0.text', '');
    }
}
