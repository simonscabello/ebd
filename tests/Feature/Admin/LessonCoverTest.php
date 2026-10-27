<?php

namespace Tests\Feature\Admin;

use App\Actions\Lessons\SaveLessonCover;
use App\Actions\Library\PublishSeriesVersion;
use App\Enums\LessonVisibility;
use App\Events\LessonPublished;
use App\Mcp\Servers\EbdServer;
use App\Mcp\Tools\SaveLessonCoverTool;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use App\Support\OfficialLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Capa da lição: envio pela gestão e pelo MCP, entrega da imagem, feed do
 * podcast, prévia do link e cópia pela biblioteca oficial.
 */
class LessonCoverTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['ebd.materials.disk' => 'local']);

        $this->classroom = Classroom::factory()->create();
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
    }

    private function png(int $width = 1254, int $height = 1254): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 47, 95, 83));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function lesson(array $attributes = []): Lesson
    {
        return Lesson::factory()->for($this->classroom)->published()->create(['number' => 4, 'title' => 'A parede caiu', ...$attributes]);
    }

    private function upload(Lesson $lesson, string $bytes, string $name = 'capa.png'): TestResponse
    {
        return $this->actingAs($this->teacher)->post(route('admin.lessons.cover.store', $lesson), [
            'cover' => UploadedFile::fake()->createWithContent($name, $bytes),
        ]);
    }

    public function test_upload_becomes_a_light_square_jpeg_with_a_thumbnail(): void
    {
        $lesson = $this->lesson();

        $this->upload($lesson, $this->png(1600, 1200))->assertRedirect()->assertSessionHasNoErrors();

        $lesson->refresh();
        $disk = Storage::disk('local');
        [$width, $height, $type] = getimagesizefromstring($disk->get($lesson->cover_path));
        $this->assertSame([1400, 1400, IMAGETYPE_JPEG], [$width, $height, $type]);
        $this->assertSame(400, getimagesizefromstring($disk->get(SaveLessonCover::thumbnailPath($lesson->cover_path)))[0]);
        $this->assertLessThan(300 * 1024, $disk->size($lesson->cover_path));

        // Trocar apaga a capa e a miniatura antigas.
        $old = $lesson->cover_path;
        $this->upload($lesson, $this->png())->assertSessionHasNoErrors();
        $this->assertFalse($disk->exists($old));
        $this->assertFalse($disk->exists(SaveLessonCover::thumbnailPath($old)));

        $this->actingAs($this->teacher)->delete(route('admin.lessons.cover.destroy', $lesson))->assertRedirect();
        $this->assertNull($lesson->fresh()->cover_path);
        $this->assertSame([], $disk->allFiles(config('ebd.covers.directory')));
    }

    public function test_rejects_small_images_files_that_are_not_images_and_students(): void
    {
        $lesson = $this->lesson();

        $this->upload($lesson, $this->png(400, 400))->assertSessionHasErrors('cover');
        $this->upload($lesson, 'não é imagem', 'capa.jpg')->assertSessionHasErrors('cover');
        $this->upload($lesson, $this->png(6001, 600))->assertSessionHasErrors('cover');

        $student = User::factory()->studentOf($this->classroom)->create();
        $this->actingAs($student)->post(route('admin.lessons.cover.store', $lesson), [
            'cover' => UploadedFile::fake()->createWithContent('capa.png', $this->png()),
        ])->assertForbidden();

        $this->assertNull($lesson->fresh()->cover_path);
    }

    public function test_cover_is_served_with_the_lesson_permission(): void
    {
        $public = $this->lesson();
        $members = $this->lesson(['title' => 'Só da classe', 'visibility' => LessonVisibility::Members]);
        app(SaveLessonCover::class)->handle($public, $this->png());
        app(SaveLessonCover::class)->handle($members, $this->png());

        $this->get($public->coverUrl())->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame(400, getimagesizefromstring($this->get($public->coverUrl(thumbnail: true))->streamedContent())[0]);
        $this->assertStringContainsString('public', (string) $this->get($public->coverUrl())->headers->get('Cache-Control'));
        $this->get($members->coverUrl())->assertNotFound();
        $restricted = $this->actingAs($this->teacher)->get($members->coverUrl())->assertOk();
        $this->assertStringContainsString('private', (string) $restricted->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string) $restricted->headers->get('Cache-Control'));
    }

    public function test_feed_and_link_preview_use_the_cover_of_public_lessons(): void
    {
        config(['ebd.podcast_email' => 'podcast@example.com']);
        Storage::disk('local')->put('audio/x.mp3', 'mp3');
        $lesson = $this->lesson(['audio_disk' => 'local', 'audio_path' => 'audio/x.mp3', 'audio_generated_at' => now()]);
        app(SaveLessonCover::class)->handle($lesson, $this->png());

        $xml = simplexml_load_string($this->get(route('churches.podcast', $this->classroom->church))->getContent());
        $xml->registerXPathNamespace('itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd');
        $this->assertSame([$lesson->coverUrl()], array_map(fn ($node) => (string) $node['href'], $xml->xpath('//item/itunes:image')));

        $this->get(route('lessons.show', $lesson->slug))
            ->assertSee('<meta property="og:image" content="'.e($lesson->coverUrl()).'">', false);
    }

    public function test_mcp_saves_the_cover_of_a_published_lesson_and_records_it(): void
    {
        Event::fake([LessonPublished::class]);
        $lesson = $this->lesson();

        EbdServer::actingAs($this->teacher)->tool(SaveLessonCoverTool::class, ['lesson_id' => $lesson->id, 'image_base64' => '***'])
            ->assertHasErrors(['base64 válido']);
        EbdServer::actingAs($this->teacher)->tool(SaveLessonCoverTool::class, ['lesson_id' => $lesson->id, 'image_base64' => base64_encode($this->png())])
            ->assertOk()->assertSee('updated');

        $this->assertNotNull($lesson->fresh()->cover_path);
        $this->assertSame(['save_lesson_cover'], AuditLog::query()->pluck('tool')->all());
        Event::assertNotDispatched(LessonPublished::class);

        EbdServer::actingAs($this->teacher)->tool(SaveLessonCoverTool::class, ['lesson_id' => $lesson->id, 'remove' => true])
            ->assertOk()->assertSee('removed');
        $this->assertNull($lesson->fresh()->cover_path);
    }

    public function test_official_library_freezes_and_copies_the_cover(): void
    {
        Event::fake();
        $owner = User::factory()->admin()->create();
        $official = Series::factory()->for(OfficialLibrary::classroom())->create();
        $source = Lesson::factory()->for(OfficialLibrary::classroom())->forSeries($official)->number(1)->published()->create();
        app(SaveLessonCover::class)->handle($source, $this->png());

        $version = app(PublishSeriesVersion::class)->handle($owner, $official, null);
        $frozen = $version->snapshot['lessons'][0]['cover']['path'];
        $this->assertNotSame($source->fresh()->cover_path, $frozen);

        // Trocar a capa do original depois não afeta a versão publicada.
        app(SaveLessonCover::class)->handle($source->fresh(), $this->png());

        $target = Classroom::factory()->for(Church::factory())->create();
        $this->actingAs(User::factory()->teacherOf($target)->create())
            ->post(route('admin.library.copy', $official), ['classroom_id' => $target->id]);

        $copy = Lesson::query()->where('classroom_id', $target->id)->sole();
        $this->assertTrue($copy->hasCover());
        $this->assertSame(Storage::disk('local')->get($frozen), Storage::disk('local')->get($copy->cover_path));
        $this->assertTrue(Storage::disk('local')->exists(SaveLessonCover::thumbnailPath($copy->cover_path)));
    }
}
