<?php

namespace Tests\Feature\Library;

use App\Actions\Library\PublishSeriesVersion;
use App\Enums\BibleBook;
use App\Events\LessonPublished;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\LessonMaterial;
use App\Models\LessonReading;
use App\Models\Series;
use App\Models\SeriesVersion;
use App\Models\User;
use App\Support\OfficialLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeBible;
use Tests\TestCase;

/**
 * Qualquer igreja usa um estudo oficial numa turma: a turma recebe uma cópia
 * editável, em rascunho, que não depende mais do original.
 */
class CopySeriesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Series $official;

    private SeriesVersion $version;

    private Classroom $classroomB;

    private User $teacherB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['ebd.materials.disk' => 'local']);

        Classroom::factory()->create();
        $this->owner = User::factory()->admin()->create();
        $library = OfficialLibrary::classroom();
        $this->official = Series::factory()->for($library)->create(['title' => 'Fora de Contexto', 'description' => 'Versículos tirados do lugar.']);
        $lesson = Lesson::factory()->for($library)->forSeries($this->official)->number(1)->published()->create([
            'title' => 'Jeremias 29.11',
            'content' => "## Contexto\n\nO exílio (Jr 29.1).",
            'bible_reference' => 'Jr 29.1-14',
        ]);
        LessonBlock::factory()->for($lesson)->create(['title' => 'Curiosidade', 'body' => 'Babilônia.']);
        LessonReading::factory()->for($lesson)->create(['weekday' => 1, 'reference' => 'Jr 29.1-7']);
        Storage::disk('local')->put("materials/{$lesson->id}/roteiro.pdf", '%PDF-1.4');
        LessonMaterial::factory()->for($lesson)->withFile("materials/{$lesson->id}/roteiro.pdf")->create(['title' => 'Roteiro']);
        $this->version = app(PublishSeriesVersion::class)->handle($this->owner, $this->official, null);

        $this->classroomB = Classroom::factory()->for(Church::factory())->create();
        $this->teacherB = User::factory()->teacherOf($this->classroomB)->create();
    }

    private function copy(?User $as = null, ?Classroom $to = null): TestResponse
    {
        return $this->actingAs($as ?? $this->teacherB)
            ->post(route('admin.library.copy', $this->official), ['classroom_id' => ($to ?? $this->classroomB)->id]);
    }

    public function test_a_teacher_copies_the_study_into_their_classroom_as_drafts(): void
    {
        Event::fake();

        $response = $this->copy();

        $copy = Series::query()->where('classroom_id', $this->classroomB->id)->sole();
        $response->assertRedirect(route('admin.series.edit', $copy));
        $this->assertSame('Fora de Contexto', $copy->title);
        $this->assertSame($this->official->id, $copy->source_series_id);
        $this->assertSame(1, $copy->source_version);

        $lesson = $copy->lessons()->sole();
        $this->assertSame($this->classroomB->id, $lesson->classroom_id);
        $this->assertSame('draft', $lesson->status->value);
        $this->assertSame($this->teacherB->id, $lesson->created_by);
        $this->assertSame("## Contexto\n\nO exílio (Jr 29.1).", $lesson->content);
        $this->assertSame($this->version->snapshot['lessons'][0]['source_lesson_id'], $lesson->source_lesson_id);
        $this->assertNull($lesson->audio_path);
        $this->assertSame(['Babilônia.'], $lesson->blocks()->pluck('body')->all());
        $this->assertSame(['Jr 29.1-7'], $lesson->readings()->pluck('reference')->all());

        $material = $lesson->materials()->sole();
        $this->assertStringStartsWith("materials/{$lesson->id}/", $material->path);
        Storage::disk('local')->assertExists($material->path);

        Event::assertNotDispatched(LessonPublished::class);
    }

    public function test_the_copy_survives_the_original_being_deleted(): void
    {
        $this->copy();
        $official = $this->official->lessons()->sole();
        $official->materials()->each(fn ($m) => Storage::disk('local')->delete($m->path));
        $official->forceDelete();

        $copy = Series::query()->where('classroom_id', $this->classroomB->id)->sole()->lessons()->sole();
        $this->assertSame('Jeremias 29.11', $copy->title);
        Storage::disk('local')->assertExists($copy->materials()->sole()->path);
    }

    public function test_copying_twice_gives_different_slugs(): void
    {
        $this->copy()->assertSessionHasNoErrors();
        $this->copy()->assertSessionHasNoErrors();

        $this->assertSame(2, Series::query()->where('classroom_id', $this->classroomB->id)->count());
        $this->assertSame(2, Lesson::query()->where('classroom_id', $this->classroomB->id)->distinct()->count('slug'));
    }

    public function test_nobody_copies_into_a_classroom_they_do_not_manage(): void
    {
        $simonsClassroom = Classroom::query()->whereHas('church', fn ($q) => $q->where('is_official', false))->orderBy('id')->first();

        $this->copy(to: $simonsClassroom)->assertSessionHasErrors('classroom_id');

        $student = User::factory()->studentOf($this->classroomB)->create();
        $this->copy($student)->assertForbidden();

        $this->assertSame(0, Series::query()->where('source_series_id', $this->official->id)->count());
    }

    public function test_the_library_lists_published_official_studies(): void
    {
        Series::factory()->for(OfficialLibrary::classroom())->create(['title' => 'Sem versão']);

        $this->actingAs($this->teacherB)
            ->get(route('admin.library.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/library/index')
                ->has('studies', 1)
                ->where('studies.0.title', 'Fora de Contexto')
                ->where('studies.0.lessons_count', 1)
                ->where('studies.0.version', 1));

        $student = User::factory()->studentOf($this->classroomB)->create();
        $this->actingAs($student)->get(route('admin.library.index'))->assertForbidden();
    }

    public function test_the_study_page_has_no_bible_text(): void
    {
        FakeBible::seed([BibleBook::Jeremiah->value => [29 => 14]]);

        $this->actingAs($this->teacherB)
            ->get(route('admin.library.show', $this->official))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/library/show')
                ->where('study.lessons.0.title', 'Jeremias 29.11')
                ->where('study.lessons.0.bible_reference', 'Jr 29.1-14')
                ->where('classrooms.0.id', $this->classroomB->id))
            ->assertDontSee('Jeremias 29.2');

        $common = Series::factory()->for($this->classroomB)->create();
        $this->actingAs($this->teacherB)->get(route('admin.library.show', $common))->assertNotFound();
    }
}
