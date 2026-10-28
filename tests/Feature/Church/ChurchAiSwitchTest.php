<?php

namespace Tests\Feature\Church;

use App\Enums\AiUsageKind;
use App\Models\AiUsage;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A IA (dúvidas e áudio) só funciona na igreja em que o dono liberou, nos
 * limites dela. A primeira igreja (a do dono) continua como hoje.
 */
class ChurchAiSwitchTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private Classroom $classroom;

    private User $student;

    private User $teacher;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['services.openai.key' => 'sk-test', 'ebd.materials.disk' => 'local', 'ebd.audio.script' => false, 'ebd.audio.music' => null]);

        Classroom::factory()->create(); // a primeira igreja, do dono
        $this->church = Church::factory()->create();
        $this->classroom = Classroom::factory()->for($this->church)->create();
        $this->student = User::factory()->studentOf($this->classroom)->create();
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
        $this->lesson = Lesson::factory()->for($this->classroom)->published()->create([
            'slug' => 'licao-b',
            'content' => "## Introdução\n\nEstudo da igreja B.",
        ]);
    }

    private function fakeAnswer(): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Resposta.']]]],
        ])]);
    }

    public function test_a_church_without_ai_has_no_helper(): void
    {
        $this->actingAs($this->student)
            ->get('/licoes/licao-b')
            ->assertInertia(fn (Assert $page) => $page->where('helper', null));

        $this->actingAs($this->student)
            ->postJson('/licoes/licao-b/duvidas', ['question' => 'Quem era?'])
            ->assertForbidden();

        $this->assertDatabaseCount('study_questions', 0);
    }

    public function test_the_daily_limit_comes_from_the_church_and_usage_is_recorded(): void
    {
        $this->fakeAnswer();
        $this->church->update(['helper_enabled' => true, 'helper_daily_limit' => 2]);

        $this->actingAs($this->student)
            ->get('/licoes/licao-b')
            ->assertInertia(fn (Assert $page) => $page->where('helper.daily_limit', 2)->where('helper.remaining', 2));

        $this->actingAs($this->student)->postJson('/licoes/licao-b/duvidas', ['question' => 'Um?'])->assertOk()->assertJson(['remaining' => 1]);
        $this->actingAs($this->student)->postJson('/licoes/licao-b/duvidas', ['question' => 'Dois?'])->assertOk()->assertJson(['remaining' => 0]);
        $this->actingAs($this->student)->postJson('/licoes/licao-b/duvidas', ['question' => 'Três?'])->assertUnprocessable();

        $this->assertDatabaseCount('study_questions', 2);
        $this->assertSame(2, $this->church->aiUsages()->where('kind', 'question')->count());
    }

    public function test_a_church_without_audio_cannot_generate_it(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('admin.lessons.edit', $this->lesson))
            ->assertInertia(fn (Assert $page) => $page->where('audio.enabled', false));

        $this->actingAs($this->teacher)
            ->post(route('admin.lessons.audio', $this->lesson))
            ->assertForbidden();

        Http::assertNothingSent();
        $this->assertNull($this->lesson->fresh()->audio_status);
    }

    public function test_the_monthly_audio_limit_counts_generated_audios(): void
    {
        $this->church->update(['audio_enabled' => true, 'audio_monthly_limit' => 1]);
        Http::fake(['api.openai.com/v1/audio/speech' => Http::response(str_repeat("\xFF\xF3\xC4\xC4".str_repeat("\0", 380), 50), 200, ['Content-Type' => 'audio/mpeg'])]);

        $this->actingAs($this->teacher)->post(route('admin.lessons.audio', $this->lesson))->assertSessionHasNoErrors();
        $this->assertTrue($this->lesson->fresh()->hasAudio());
        $this->assertSame(1, $this->church->aiUsages()->where('kind', 'audio')->count());

        $other = Lesson::factory()->for($this->classroom)->published()->create(['content' => 'Outro estudo.']);

        $this->actingAs($this->teacher)
            ->get(route('admin.lessons.edit', $other))
            ->assertInertia(fn (Assert $page) => $page->where('audio.enabled', true)->where('audio.remaining', 0));

        $this->actingAs($this->teacher)
            ->post(route('admin.lessons.audio', $other))
            ->assertSessionHasErrors(['audio' => 'Limite de áudios do mês atingido (1 por mês).']);

        $this->assertNull($other->fresh()->audio_status);
    }

    public function test_the_monthly_audio_limit_counts_failed_attempts(): void
    {
        $this->church->update(['audio_enabled' => true, 'audio_monthly_limit' => 1]);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $this->actingAs($this->teacher)->post(route('admin.lessons.audio', $this->lesson))->assertSessionHasNoErrors();
        $this->assertSame(Lesson::AUDIO_FAILED, $this->lesson->fresh()->audio_status);

        // "Gerar de novo" não passa do limite: a tentativa que falhou já gastou.
        $this->actingAs($this->teacher)
            ->post(route('admin.lessons.audio', $this->lesson))
            ->assertSessionHasErrors(['audio' => 'Limite de áudios do mês atingido (1 por mês).']);
        $this->assertSame(1, $this->church->aiUsages()->where('kind', 'audio')->count());
    }

    public function test_an_audio_being_generated_takes_its_place_in_the_limit(): void
    {
        $this->church->update(['audio_enabled' => true, 'audio_monthly_limit' => 2]);
        Http::fake(['api.openai.com/v1/audio/speech' => Http::response(str_repeat("\xFF\xF3\xC4\xC4".str_repeat("\0", 380), 50), 200, ['Content-Type' => 'audio/mpeg'])]);

        $this->actingAs($this->teacher)->post(route('admin.lessons.audio', $this->lesson));
        // Outra lição pedida e ainda gerando: já ocupa a última vaga.
        $other = Lesson::factory()->for($this->classroom)->published()->create(['content' => 'Outro estudo.']);
        $other->forceFill(['audio_status' => Lesson::AUDIO_GENERATING, 'audio_requested_at' => now()])->save();
        AiUsage::record($this->church, AiUsageKind::Audio, $other);

        $third = Lesson::factory()->for($this->classroom)->published()->create(['content' => 'Mais um estudo.']);
        $this->actingAs($this->teacher)
            ->post(route('admin.lessons.audio', $third))
            ->assertSessionHasErrors(['audio' => 'Limite de áudios do mês atingido (2 por mês).']);

        // Clicar de novo na lição que está gerando não esbarra no limite.
        $this->actingAs($this->teacher)->post(route('admin.lessons.audio', $other))->assertSessionHasNoErrors();
        $this->assertSame(2, $this->church->aiUsages()->where('kind', 'audio')->count());
    }

    public function test_the_first_church_keeps_ai_and_audio_on(): void
    {
        $first = Church::primary();

        $this->assertTrue($first->helper_enabled);
        $this->assertTrue($first->audio_enabled);
        $this->assertTrue($first->shows_bible_text);
        $this->assertFalse($this->church->fresh()->helper_enabled);
    }

    public function test_a_second_request_waits_while_another_lesson_takes_the_last_audio(): void
    {
        $this->church->update(['audio_enabled' => true, 'audio_monthly_limit' => 1]);
        $lock = Cache::lock("church-audio:{$this->church->id}", 10);
        $lock->get();

        $this->actingAs($this->teacher)
            ->post(route('admin.lessons.audio', $this->lesson))
            ->assertSessionHasErrors(['audio' => 'Outro áudio desta igreja está sendo pedido agora. Tente de novo em instantes.']);

        $lock->release();
        $this->assertNull($this->lesson->fresh()->audio_status);
    }
}
