<?php

namespace Tests\Feature;

use App\Enums\ContentAudience;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\StudyQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * "Tirar dúvida": o membro da classe pergunta à IA sobre a lição.
 */
class StudyQuestionTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $student;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.key' => 'sk-test', 'ebd.helper.daily_limit' => 2]);

        $this->classroom = Classroom::factory()->create();
        $this->student = User::factory()->studentOf($this->classroom)->create();
        $this->lesson = Lesson::factory()->for($this->classroom)->published()->create([
            'slug' => 'e-necessario',
            'title' => 'É Necessário',
            'content' => 'Jesus cura o cego de nascença.',
        ]);
    }

    private function fakeOpenAi(string $answer = 'O cego representa todos nós.'): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $answer]]]],
        ])]);
    }

    public function test_member_asks_and_gets_an_answer_based_on_the_lesson(): void
    {
        $this->fakeOpenAi();
        LessonBlock::factory()->for($this->lesson)->create(['audience' => ContentAudience::Student, 'body' => 'Curiosidade para o aluno']);
        LessonBlock::factory()->for($this->lesson)->create(['audience' => ContentAudience::Teacher, 'body' => 'Resposta só do professor']);

        $this->actingAs($this->student)
            ->postJson('/licoes/e-necessario/duvidas', ['question' => 'Quem era o cego?'])
            ->assertOk()
            ->assertJson(['answer' => 'O cego representa todos nós.', 'remaining' => 1]);

        Http::assertSent(function (Request $request) {
            $input = (string) $request['input'];

            return str_contains($input, 'Jesus cura o cego de nascença.')
                && str_contains($input, 'Curiosidade para o aluno')
                && ! str_contains($input, 'Resposta só do professor')
                && str_ends_with($input, 'Quem era o cego?');
        });

        $this->assertDatabaseHas('study_questions', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson->id,
            'question' => 'Quem era o cego?',
        ]);
    }

    public function test_daily_limit(): void
    {
        $this->fakeOpenAi();

        StudyQuestion::query()->create(['user_id' => $this->student->id, 'lesson_id' => $this->lesson->id, 'question' => 'a', 'answer' => 'b']);
        StudyQuestion::query()->create(['user_id' => $this->student->id, 'lesson_id' => $this->lesson->id, 'question' => 'a', 'answer' => 'b']);

        $this->actingAs($this->student)
            ->postJson('/licoes/e-necessario/duvidas', ['question' => 'Mais uma?'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question');

        Http::assertNothingSent();

        // No dia seguinte, volta.
        $this->travel(1)->days();

        $this->actingAs($this->student)
            ->postJson('/licoes/e-necessario/duvidas', ['question' => 'Mais uma?'])
            ->assertOk();
    }

    public function test_openai_failure_shows_a_friendly_message_and_does_not_count(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'x']], 400)]);

        $this->actingAs($this->student)
            ->postJson('/licoes/e-necessario/duvidas', ['question' => 'Quem era o cego?'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['question' => 'Não consegui responder agora.']);

        $this->assertDatabaseCount('study_questions', 0);
    }

    public function test_only_members_of_the_class_can_ask(): void
    {
        $this->fakeOpenAi();
        $outsider = User::factory()->studentOf(Classroom::factory()->create())->create();

        $this->postJson('/licoes/e-necessario/duvidas', ['question' => 'Quem era o cego?'])->assertUnauthorized();

        $this->actingAs($outsider)
            ->postJson('/licoes/e-necessario/duvidas', ['question' => 'Quem era o cego?'])
            ->assertForbidden();

        $this->get('/licoes/e-necessario')->assertInertia(fn (Assert $page) => $page->where('helper', null));
        $this->actingAs($outsider)->get('/licoes/e-necessario')->assertInertia(fn (Assert $page) => $page->where('helper', null));

        Http::assertNothingSent();
    }

    public function test_lesson_page_offers_the_helper_to_members(): void
    {
        $this->actingAs($this->student)
            ->get('/licoes/e-necessario')
            ->assertInertia(fn (Assert $page) => $page
                ->where('helper.remaining', 2)
                ->where('helper.daily_limit', 2));
    }

    public function test_helper_is_hidden_without_the_openai_key(): void
    {
        config(['services.openai.key' => null]);

        $this->actingAs($this->student)
            ->get('/licoes/e-necessario')
            ->assertInertia(fn (Assert $page) => $page->where('helper', null));
    }
}
