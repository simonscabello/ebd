<?php

namespace Tests\Feature\Admin;

use App\Enums\MaterialType;
use App\Http\Resources\LessonMaterialResource;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LessonMaterialTest extends TestCase
{
    use RefreshDatabase;

    private Lesson $lesson;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['ebd.materials.disk' => 'local']);

        $classroom = Classroom::factory()->create();
        $this->teacher = User::factory()->teacherOf($classroom)->create();
        $this->lesson = Lesson::factory()->for($classroom)->create();
    }

    private function url(): string
    {
        return "/admin/licoes/{$this->lesson->id}/materiais";
    }

    public function test_teacher_uploads_a_pdf_to_private_storage(): void
    {
        $pdf = UploadedFile::fake()->create('Lição 5.pdf', 300, 'application/pdf');

        $this->actingAs($this->teacher)->post($this->url(), [
            'type' => 'pdf',
            'title' => 'Revista',
            'file' => $pdf,
            'is_primary' => true,
        ])->assertSessionHasNoErrors();

        $material = LessonMaterial::query()->sole();
        $this->assertSame(MaterialType::Pdf, $material->type);
        $this->assertTrue($material->is_primary);
        $this->assertSame('Lição 5.pdf', $material->original_name);
        $this->assertSame('application/pdf', $material->mime_type);
        $this->assertStringStartsWith("materials/{$this->lesson->id}/", (string) $material->path);
        $this->assertStringNotContainsString('Lição', (string) $material->path);
        Storage::disk('local')->assertExists((string) $material->path);
    }

    public function test_pdf_upload_rejects_wrong_extension_mime_and_size(): void
    {
        $this->actingAs($this->teacher)->post($this->url(), [
            'type' => 'pdf', 'title' => 'Falso', 'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->actingAs($this->teacher)->post($this->url(), [
            'type' => 'pdf', 'title' => 'Falso', 'file' => UploadedFile::fake()->create('script.pdf', 10, 'text/html'),
        ])->assertSessionHasErrors('file');

        config(['ebd.materials.max_upload_kb' => 100]);
        $this->actingAs($this->teacher)->post($this->url(), [
            'type' => 'pdf', 'title' => 'Grande', 'file' => UploadedFile::fake()->create('grande.pdf', 101, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->actingAs($this->teacher)->post($this->url(), [
            'type' => 'pdf', 'title' => 'Sem arquivo',
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('lesson_materials', 0);
    }

    public function test_links_videos_and_references_do_not_need_files(): void
    {
        $this->actingAs($this->teacher)->post($this->url(), [
            'type' => 'video', 'title' => 'Vídeo', 'url' => 'https://youtu.be/abcdefghijk',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->teacher)->post($this->url(), [
            'type' => 'reference', 'title' => 'O Conhecimento do Santo — A. W. Tozer',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->teacher)->post($this->url(), [
            'type' => 'link', 'title' => 'Perigoso', 'url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('url');

        $this->actingAs($this->teacher)->post($this->url(), [
            'type' => 'audio', 'title' => 'Áudio sem fonte',
        ])->assertSessionHasErrors('file');

        $materials = $this->lesson->materials()->get();
        $this->assertSame([1, 2], $materials->pluck('position')->all());
        $this->assertSame(MaterialType::Video, $materials->first()->type);
    }

    public function test_video_embed_only_for_valid_youtube_ids(): void
    {
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/abcdefghijk',
            LessonMaterialResource::videoEmbedUrl('https://www.youtube.com/watch?v=abcdefghijk&t=10'),
        );
        $this->assertNull(LessonMaterialResource::videoEmbedUrl('https://evil.test/watch?v=abcdefghijk'));
    }

    public function test_removing_a_material_deletes_its_file(): void
    {
        $this->actingAs($this->teacher)->post($this->url(), [
            'type' => 'pdf', 'title' => 'Revista', 'file' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'),
        ]);
        $material = LessonMaterial::query()->sole();

        $this->actingAs($this->teacher)->delete("{$this->url()}/{$material->id}")->assertRedirect();

        $this->assertModelMissing($material);
        Storage::disk('local')->assertMissing((string) $material->path);
    }

    public function test_material_routes_are_scoped_to_the_lesson(): void
    {
        $otherMaterial = LessonMaterial::factory()->create();

        $this->actingAs($this->teacher)->delete("{$this->url()}/{$otherMaterial->id}")->assertNotFound();
        $this->assertModelExists($otherMaterial);
    }

    public function test_students_cannot_add_materials(): void
    {
        $student = User::factory()->studentOf($this->lesson->classroom)->create();

        $this->actingAs($student)->post($this->url(), [
            'type' => 'link', 'title' => 'x', 'url' => 'https://example.com',
        ])->assertForbidden();
    }
}
