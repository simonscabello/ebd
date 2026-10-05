<?php

namespace Tests\Feature\Admin;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * "Gerar áudio" do estudo pela OpenAI e o player "Ouvir estudo".
 */
class LessonAudioTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::preventStrayRequests();
        config(['services.openai.key' => 'sk-test', 'ebd.materials.disk' => 'local']);

        $this->classroom = Classroom::factory()->create();
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
    }

    /** Quadros MP3 de verdade (MPEG-2, camada III, 128 kbps, 24 kHz): 0,024 s cada. */
    private function mp3(int $frames): string
    {
        return str_repeat("\xFF\xF3\xC4\xC4".str_repeat("\0", 380), $frames);
    }

    private function fakeOpenAi(int $framesPerChunk = 250): void
    {
        Http::fake([
            'api.openai.com/v1/audio/speech' => Http::response($this->mp3($framesPerChunk), 200, ['Content-Type' => 'audio/mpeg']),
        ]);
    }

    private function lesson(array $attributes = []): Lesson
    {
        return Lesson::factory()->for($this->classroom)->published()->create([
            'number' => 3,
            'title' => 'O sinal de Jonas',
            'content' => "## Introdução\n\nOs fariseus pedem um **sinal** (Mt 12.38-40).\n\n## Aplicação\n\nJesus é maior que Jonas.",
            ...$attributes,
        ]);
    }

    public function test_teacher_generates_the_audio_once_and_it_is_stored(): void
    {
        $this->fakeOpenAi();
        $lesson = $this->lesson();

        $this->actingAs($this->teacher)
            ->post(route('admin.lessons.audio', $lesson))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $input = (string) $request['input'];

            return $request->hasHeader('Authorization', 'Bearer sk-test')
                && $request['model'] === config('ebd.audio.model')
                && $request['response_format'] === 'mp3'
                && str_starts_with($input, "Lição 3 — O sinal de Jonas.\n\nIntrodução.")
                && str_contains($input, 'um sinal (Mateus, capítulo 12, versículos 38 a 40).')
                && ! str_contains($input, '**')
                && ! str_contains($input, '##');
        });

        $lesson->refresh();
        $this->assertTrue($lesson->hasAudio());
        $this->assertNull($lesson->audio_status);
        $this->assertSame(6, $lesson->audio_duration);
        $this->assertFalse($lesson->isAudioStale());
        Storage::disk('local')->assertExists($lesson->audio_path);

        // Já existe e está atualizado: não gera de novo.
        $this->actingAs($this->teacher)->post(route('admin.lessons.audio', $lesson));
        Http::assertSentCount(1);
    }

    public function test_long_study_is_split_by_sections_into_a_single_file(): void
    {
        config(['ebd.audio.max_chars' => 120]);
        $this->fakeOpenAi(framesPerChunk: 100);
        $paragraph = 'Jesus ensinava às multidões com parábolas, e os discípulos perguntavam o sentido.';
        $lesson = $this->lesson([
            'content' => "## Primeira parte\n\n{$paragraph}\n\n{$paragraph}\n\n## Segunda parte\n\n{$paragraph}",
        ]);

        $this->actingAs($this->teacher)->post(route('admin.lessons.audio', $lesson));

        $inputs = Http::recorded()->map(fn (array $pair) => (string) $pair[0]['input'])->all();

        $this->assertGreaterThan(1, count($inputs));
        foreach ($inputs as $input) {
            $this->assertLessThanOrEqual(120, mb_strlen($input));
        }
        $this->assertStringStartsWith('Segunda parte.', end($inputs));

        $lesson->refresh();
        $this->assertSame(strlen($this->mp3(100)) * count($inputs), strlen((string) Storage::disk('local')->get($lesson->audio_path)));
    }

    public function test_editing_the_study_marks_the_audio_as_stale_and_allows_regenerating(): void
    {
        $this->fakeOpenAi();
        $lesson = $this->lesson();
        $this->actingAs($this->teacher)->post(route('admin.lessons.audio', $lesson));
        $oldPath = $lesson->refresh()->audio_path;

        $lesson->update(['content' => $lesson->content."\n\nUm parágrafo novo."]);

        $this->actingAs($this->teacher)->get(route('lessons.show', $lesson->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('audio.manage.stale', true)
                ->where('audio.manage.status', 'ready'));

        $this->actingAs($this->teacher)->post(route('admin.lessons.audio', $lesson));

        Http::assertSentCount(2);
        $lesson->refresh();
        $this->assertFalse($lesson->isAudioStale());
        $this->assertNotSame($oldPath, $lesson->audio_path);
        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_repeated_clicks_while_generating_do_not_start_another_generation(): void
    {
        $this->fakeOpenAi();
        $lesson = $this->lesson();
        $lesson->forceFill(['audio_status' => Lesson::AUDIO_GENERATING, 'audio_requested_at' => now()->subMinute()])->save();

        $this->actingAs($this->teacher)
            ->post(route('admin.lessons.audio', $lesson))
            ->assertSessionHasNoErrors();

        Http::assertNothingSent();
        $this->assertSame(Lesson::AUDIO_GENERATING, $lesson->refresh()->audio_status);

        $this->actingAs($this->teacher)->get(route('lessons.show', $lesson->slug))
            ->assertInertia(fn (Assert $page) => $page->where('audio.manage.status', 'generating'));
    }

    public function test_api_errors_become_a_friendly_message(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['code' => 'insufficient_quota', 'message' => 'You exceeded your current quota']], 429),
        ]);
        $lesson = $this->lesson();

        $this->actingAs($this->teacher)->post(route('admin.lessons.audio', $lesson));

        $lesson->refresh();
        $this->assertFalse($lesson->hasAudio());
        $this->assertSame(Lesson::AUDIO_FAILED, $lesson->audio_status);

        $this->actingAs($this->teacher)->get(route('lessons.show', $lesson->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('audio.file', null)
                ->where('audio.manage.status', 'failed')
                ->where('audio.manage.error', 'A conta da OpenAI está sem créditos ou passou do limite de uso.'));
    }

    public function test_generation_needs_the_key_and_a_study(): void
    {
        $lesson = $this->lesson();

        config(['services.openai.key' => null]);
        $this->actingAs($this->teacher)->post(route('admin.lessons.audio', $lesson))->assertSessionHasErrors('audio');

        config(['services.openai.key' => 'sk-test']);
        $empty = $this->lesson(['content' => null]);
        $this->actingAs($this->teacher)->post(route('admin.lessons.audio', $empty))->assertSessionHasErrors('audio');

        Http::assertNothingSent();
    }

    public function test_students_only_get_the_player_and_cannot_generate(): void
    {
        $this->fakeOpenAi();
        $lesson = $this->lesson();
        $student = User::factory()->studentOf($this->classroom)->create();

        $this->actingAs($student)->get(route('lessons.show', $lesson->slug))
            ->assertInertia(fn (Assert $page) => $page->where('audio', null));
        $this->actingAs($student)->post(route('admin.lessons.audio', $lesson))->assertForbidden();

        $this->actingAs($this->teacher)->post(route('admin.lessons.audio', $lesson));
        $lesson->refresh();

        $this->actingAs($student)->get(route('lessons.show', $lesson->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('audio.file.duration', 6)
                ->missing('audio.manage'));

        $this->actingAs($student)->get(route('lessons.audio', $lesson->slug))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg')
            ->assertHeader('Accept-Ranges', 'bytes');

        // Rascunho: o áudio segue a permissão de leitura da lição.
        $lesson->forceFill(['status' => 'draft'])->save();
        auth()->logout();
        $this->get(route('lessons.audio', $lesson->slug))->assertNotFound();
    }
}
