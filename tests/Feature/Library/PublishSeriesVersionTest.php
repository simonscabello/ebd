<?php

namespace Tests\Feature\Library;

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
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * O dono publica uma versão de um estudo oficial: um retrato fixo da série
 * (só as lições publicadas), que as igrejas copiam.
 */
class PublishSeriesVersionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Series $series;

    private Lesson $first;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['ebd.materials.disk' => 'local']);

        Classroom::factory()->create(); // igreja do dono
        $this->owner = User::factory()->admin()->create();
        $library = OfficialLibrary::classroom();

        $this->series = Series::factory()->for($library)->create(['title' => 'Fora de Contexto', 'description' => 'Versículos tirados do lugar.']);
        $this->first = Lesson::factory()->for($library)->forSeries($this->series)->number(1)->published()->create([
            'title' => 'Jeremias 29.11',
            'content' => "## Contexto\n\nO exílio.",
            'bible_reference' => 'Jr 29.1-14',
        ]);
        LessonBlock::factory()->for($this->first)->create(['title' => 'Curiosidade', 'body' => 'Babilônia.']);
        LessonReading::factory()->for($this->first)->create(['weekday' => 1, 'reference' => 'Jr 29.1-7']);
        LessonMaterial::factory()->for($this->first)->create(['title' => 'Vídeo', 'url' => 'https://example.test/video']);
        Lesson::factory()->for($library)->forSeries($this->series)->number(2)->create(['title' => 'Rascunho']);
    }

    private function publish(?User $as = null, array $data = []): TestResponse
    {
        return $this->actingAs($as ?? $this->owner)->post(route('admin.series.versions.store', $this->series), $data);
    }

    public function test_the_owner_publishes_version_1_with_only_published_lessons(): void
    {
        $this->publish(data: ['notes' => 'Primeira versão.'])->assertRedirect()->assertSessionHasNoErrors();

        $version = SeriesVersion::query()->sole();
        $this->assertSame(1, $version->version);
        $this->assertSame('Primeira versão.', $version->notes);
        $this->assertSame('Fora de Contexto', $version->snapshot['title']);
        $this->assertCount(1, $version->snapshot['lessons']);

        $lesson = $version->snapshot['lessons'][0];
        $this->assertSame($this->first->id, $lesson['source_lesson_id']);
        $this->assertSame('Jeremias 29.11', $lesson['title']);
        $this->assertSame('Jr 29.1-14', $lesson['bible_reference']);
        $this->assertSame('Babilônia.', $lesson['blocks'][0]['body']);
        $this->assertSame('Jr 29.1-7', $lesson['readings'][0]['reference']);
        $this->assertSame('https://example.test/video', $lesson['materials'][0]['url']);

        $this->publish()->assertSessionHasNoErrors();
        $this->assertSame([1, 2], SeriesVersion::query()->orderBy('version')->pluck('version')->all());
    }

    public function test_editing_the_lesson_later_does_not_change_the_published_version(): void
    {
        $this->publish();
        $this->first->update(['content' => 'Texto novo.']);

        $this->assertSame("## Contexto\n\nO exílio.", SeriesVersion::query()->sole()->snapshot['lessons'][0]['content']);
    }

    public function test_material_files_are_copied_into_the_version(): void
    {
        Storage::disk('local')->put("materials/{$this->first->id}/roteiro.pdf", '%PDF-1.4 roteiro');
        $material = LessonMaterial::factory()->for($this->first)->withFile("materials/{$this->first->id}/roteiro.pdf")->create(['title' => 'Roteiro']);
        $original = $material->path;

        $this->publish();

        $file = collect(SeriesVersion::query()->sole()->snapshot['lessons'][0]['materials'])->firstWhere('title', $material->title)['file'];
        $this->assertNotSame($original, $file['path']);
        $this->assertStringStartsWith("library/{$this->series->id}/v1/", $file['path']);
        Storage::disk('local')->assertExists($file['path']);

        Storage::disk('local')->delete($original);
        Storage::disk('local')->assertExists($file['path']);
    }

    public function test_only_the_owner_publishes_and_only_official_series(): void
    {
        $admin = User::factory()->create();
        $other = Church::factory()->create();
        $other->admins()->attach($admin);

        $this->publish($admin)->assertForbidden();

        $common = Series::factory()->for(Classroom::factory()->create())->create();
        $this->actingAs($this->owner)->post(route('admin.series.versions.store', $common))->assertNotFound();

        $this->assertSame(0, SeriesVersion::query()->count());
    }

    public function test_a_series_without_published_lessons_cannot_be_published(): void
    {
        $this->first->forceFill(['status' => 'draft', 'published_at' => null])->save();

        $this->publish()->assertSessionHasErrors(['version' => 'Publique ao menos uma lição antes.']);
    }

    public function test_the_series_page_shows_the_versions_to_the_owner(): void
    {
        $this->publish(data: ['notes' => 'Primeira.']);

        $this->actingAs($this->owner)
            ->get(route('admin.series.edit', $this->series))
            ->assertInertia(fn (Assert $page) => $page
                ->where('official.next_version', 2)
                ->where('official.versions.0.version', 1)
                ->where('official.versions.0.notes', 'Primeira.'));
    }
}
