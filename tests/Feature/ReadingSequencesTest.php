<?php

namespace Tests\Feature;

use App\Enums\AiUsageKind;
use App\Enums\MeetingStatus;
use App\Models\AiUsage;
use App\Models\BibleVerse;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Lição que dura mais de um encontro: uma sequência de leituras por semana.
 * O aluno vê só a da semana; sem sequência nova, vale a última, e a IA
 * escreve a que falta.
 */
class ReadingSequencesTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $ana;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'Europe/Madrid', 'services.openai.key' => 'sk-test']);
        // Segunda-feira, 21/09/2026: 1ª semana da lição (domingos 27/09 e 04/10).
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 21)->setTime(10, 0));

        $this->classroom = Classroom::factory()->create();
        $this->ana = User::factory()->studentOf($this->classroom)->create();
        $this->lesson = Lesson::factory()->for($this->classroom)->published()->on('2026-09-27')->create(['slug' => 'continua']);
        ClassMeeting::factory()->forLesson($this->lesson)->on('2026-10-04')->create(['status' => MeetingStatus::Planned]);
        $this->classroom->forceFill(['current_lesson_id' => $this->lesson->id])->save();

        $this->sequence(1, 'Salmos');
    }

    private function sequence(int $week, string $book): void
    {
        foreach (range(1, 7) as $weekday) {
            LessonReading::factory()->for($this->lesson)->create(['week' => $week, 'weekday' => $weekday, 'reference' => "{$book} {$weekday}"]);
        }
    }

    private function secondWeek(): void
    {
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 28)->setTime(10, 0));
    }

    /**
     * @param  list<string>|null  $references
     */
    private function fakeOpenAi(?array $references = null): void
    {
        $references ??= ['Ef 3.14-21', 'Lc 24.13-35', '1Co 2.6-16', '1Pe 1.3-9', '1Co 15.12-26', 'Sl 110', 'Cl 1.15-23'];
        $readings = array_map(fn (string $reference, int $i) => ['weekday' => $i + 1, 'reference' => $reference, 'notes' => 'Para refletir: o quê?'], $references, array_keys($references));

        Http::fake(['api.openai.com/*' => Http::response([
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($readings)]]]],
        ])]);
    }

    private function mondayReading(): string
    {
        $reference = '';

        $this->actingAs($this->ana)->get('/minha-semana')
            ->assertInertia(function (Assert $page) use (&$reference) {
                $reference = $page->toArray()['props']['week']['days'][0]['readings'][0]['reference'];
            });

        return $reference;
    }

    public function test_each_week_of_the_lesson_shows_its_own_sequence(): void
    {
        $this->sequence(2, 'Provérbios');

        $this->assertSame('Salmos 1', $this->mondayReading());

        $this->secondWeek();

        $this->assertSame('Provérbios 1', $this->mondayReading());
        $this->actingAs($this->ana)->get('/licoes/continua')
            ->assertInertia(fn (Assert $page) => $page
                ->has('lesson.readings', 7)
                ->where('lesson.readings.0.reference', 'Provérbios 1'));
    }

    public function test_a_week_without_sequence_shows_the_latest_one_before_it(): void
    {
        $this->sequence(3, 'Provérbios');
        $this->lesson->load('readings');

        $this->assertSame('Salmos 1', $this->lesson->readingsForWeek(2)->first()->reference);
        $this->assertSame('Provérbios 1', $this->lesson->readingsForWeek(3)->first()->reference);
        $this->assertSame('Provérbios 1', $this->lesson->readingsForWeek(5)->first()->reference);

        $this->secondWeek();
        $this->assertSame('Salmos 1', $this->mondayReading());
    }

    public function test_ai_writes_the_sequence_of_the_week_that_has_none(): void
    {
        $this->fakeOpenAi();

        // Na 1ª semana não falta nada.
        $this->artisan('ebd:generate-readings')->assertSuccessful();
        Http::assertNothingSent();

        $this->secondWeek();
        $this->artisan('ebd:generate-readings')->assertSuccessful();
        $this->artisan('ebd:generate-readings')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains((string) $request['input'], 'Semana 1: Salmos 1'));
        $this->assertSame(7, $this->lesson->readings()->where('week', 2)->count());
        $this->assertSame(1, AiUsage::query()->where('kind', AiUsageKind::Readings)->count());
        $this->assertSame('Ef 3.14-21', $this->mondayReading());
    }

    public function test_an_answer_that_is_not_a_full_valid_week_is_discarded(): void
    {
        $this->secondWeek();

        // Com a Bíblia importada, o fim do trecho também precisa existir.
        foreach ([[1, 1], [1, 6], [2, 1]] as [$chapter, $verse]) {
            BibleVerse::query()->create(['book' => 19, 'chapter' => $chapter, 'verse' => $verse, 'text' => 'texto']);
        }

        // Livro que não existe, uma leitura que repete a da 1ª semana e um versículo que não existe.
        foreach ([['Hesitações 3.1-9'], ['Salmos 1'], ['Sl 2.1-99']] as $i => [$bad]) {
            $this->travel($i)->days();
            $this->fakeOpenAi([$bad, 'Lc 24.13-35', '1Co 2.6-16', '1Pe 1.3-9', '1Co 15.12-26', 'Sl 110', 'Cl 1.15-23']);
            $this->artisan('ebd:generate-readings')->assertSuccessful();
        }

        $this->assertSame(7, $this->lesson->readings()->count());
        $this->assertSame('Salmos 1', $this->mondayReading());
    }

    public function test_ai_stays_off_when_the_church_has_no_helper(): void
    {
        $this->fakeOpenAi();
        $this->classroom->church->forceFill(['helper_enabled' => false])->save();
        $this->secondWeek();

        $this->artisan('ebd:generate-readings')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame('Salmos 1', $this->mondayReading());
    }

    public function test_teacher_saves_a_reading_in_another_week(): void
    {
        $teacher = User::factory()->teacherOf($this->classroom)->create();

        $this->actingAs($teacher)
            ->post("/admin/licoes/{$this->lesson->id}/leituras", ['week' => 2, 'weekday' => 1, 'reference' => 'Ef 3.14-21'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $this->lesson->readings()->where('reference', 'Ef 3.14-21')->value('week'));
    }
}
