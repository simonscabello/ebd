<?php

namespace Tests\Feature\Admin;

use App\Enums\LessonStatus;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonImportTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private Series $series;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::factory()->create(['slug' => 'jovens']);
        $this->series = Series::factory()->for($this->classroom)->create(['slug' => 'milagres']);
        $this->teacher = User::factory()->teacherOf($this->classroom)->create(['email' => 'prof@ebd.test']);
    }

    public function test_it_creates_a_draft_lesson_with_readings_blocks_and_materials(): void
    {
        $this->artisan('lesson:import', ['path' => $this->lessonFile(), '--force' => true])->assertSuccessful();

        $lesson = Lesson::query()->sole();
        $this->assertSame(LessonStatus::Draft, $lesson->status);
        $this->assertSame(17, $lesson->number);
        $this->assertSame($this->series->id, $lesson->series_id);
        $this->assertSame([$this->teacher->id], $lesson->authors()->pluck('users.id')->all());
        $this->assertSame(['Mt 12.38-42', 'Jo 20.30-31'], $lesson->readings()->pluck('reference')->all());

        [$roteiro, $curiosidade] = $lesson->blocks()->get()->all();
        $this->assertSame('teacher', $roteiro->audience->value);
        $this->assertNull($roteiro->drip_weekday);
        $this->assertSame('student', $curiosidade->audience->value);
        $this->assertSame(1, $curiosidade->drip_weekday?->value);

        $material = $lesson->materials()->sole();
        $this->assertSame('reference', $material->type->value);
        $this->assertSame('student', $material->audience->value);
        $this->assertStringContainsString('dýnamis', (string) $lesson->fresh()->getRawOriginal('blocks_text'));
    }

    public function test_it_refuses_an_existing_number_unless_replacing_a_draft(): void
    {
        $this->artisan('lesson:import', ['path' => $this->lessonFile(), '--force' => true])->assertSuccessful();
        $lesson = Lesson::query()->sole();
        $upload = LessonMaterial::factory()->for($lesson)->withFile('materiais/x.pdf')->create();

        $this->artisan('lesson:import', ['path' => $this->lessonFile(), '--force' => true])->assertFailed();

        $this->artisan('lesson:import', [
            'path' => $this->lessonFile(['title' => 'Título revisto', 'readings' => [['weekday' => 3, 'reference' => 'Hb 2.1-4']]]),
            '--replace' => true,
            '--force' => true,
        ])->assertSuccessful();

        $lesson->refresh();
        $this->assertSame('Título revisto', $lesson->title);
        $this->assertSame(['Hb 2.1-4'], $lesson->readings()->pluck('reference')->all());
        $this->assertSame(2, $lesson->blocks()->count());
        $this->assertSame([$upload->id, $lesson->materials()->where('type', 'reference')->value('id')], $lesson->materials()->pluck('id')->all());
    }

    public function test_it_does_not_touch_a_published_lesson(): void
    {
        $this->artisan('lesson:import', ['path' => $this->lessonFile(), '--force' => true])->assertSuccessful();
        Lesson::query()->update(['status' => LessonStatus::Published->value]);

        $this->artisan('lesson:import', ['path' => $this->lessonFile(['title' => 'Outro']), '--replace' => true, '--force' => true])
            ->assertFailed();

        $this->assertNotSame('Outro', Lesson::query()->sole()->title);
    }

    public function test_it_validates_the_file_before_writing(): void
    {
        $this->artisan('lesson:import', [
            'path' => $this->lessonFile(['blocks' => [['kind' => 'inexistente', 'body' => 'x']], 'materials' => [['type' => 'video', 'title' => 'Sem link']]]),
            '--force' => true,
        ])->assertFailed();

        $this->assertSame(0, Lesson::query()->count());
    }

    public function test_it_lists_the_options_when_the_classroom_or_series_does_not_exist(): void
    {
        $this->artisan('lesson:import', ['path' => $this->lessonFile(['series' => 'outra']), '--force' => true])
            ->expectsOutputToContain('Séries: milagres.')
            ->assertFailed();

        $this->artisan('lesson:import', ['path' => $this->lessonFile(['classroom' => 'adultos']), '--force' => true])
            ->expectsOutputToContain('Classes: jovens.')
            ->assertFailed();
    }

    public function test_reading_from_standard_input_requires_force(): void
    {
        $this->artisan('lesson:import', ['path' => '-'])
            ->expectsOutputToContain('use --force')
            ->assertFailed();
    }

    public function test_dry_run_checks_everything_without_writing(): void
    {
        $this->artisan('lesson:import', ['path' => $this->lessonFile(), '--dry-run' => true])
            ->expectsOutputToContain('nada foi gravado')
            ->assertSuccessful();

        $this->assertSame(0, Lesson::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function lessonFile(array $overrides = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'licao').'.json';

        file_put_contents($path, json_encode(array_replace([
            'classroom' => 'jovens',
            'series' => 'milagres',
            'author' => 'prof@ebd.test',
            'number' => 17,
            'title' => 'Por que não vemos milagres como nos tempos bíblicos?',
            'summary' => 'Se Deus não mudou, por que a nossa experiência parece tão diferente?',
            'bible_reference' => 'Mt 12.38-40; Lc 11.29-30',
            'key_verse' => 'Hb 2.4',
            'goal' => 'Entender o propósito dos milagres.',
            'content' => "Introdução.\n\n## I. O que é milagre",
            'visibility' => 'public',
            'readings' => [
                ['weekday' => 1, 'reference' => 'Mt 12.38-42', 'notes' => 'Para refletir: que sinal eu peço?'],
                ['weekday' => 2, 'reference' => 'Jo 20.30-31'],
            ],
            'blocks' => [
                ['kind' => 'roteiro', 'title' => 'Roteiro da aula', 'body' => '1. Abertura (5 min)', 'drip_weekday' => 2],
                ['kind' => 'curiosity', 'title' => 'Dinamite e poder', 'body' => 'A palavra dýnamis...', 'drip_weekday' => 1],
            ],
            'materials' => [
                ['type' => 'reference', 'title' => 'Sam Storms — Porque sou um continuísta', 'description' => 'A defesa continuísta.'],
            ],
        ], $overrides)));

        return $path;
    }
}
