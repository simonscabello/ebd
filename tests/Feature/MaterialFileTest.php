<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Arquivos privados seguem a permissão de leitura da lição.
 */
class MaterialFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function materialFor(Lesson $lesson): LessonMaterial
    {
        Storage::disk('local')->put('materials/'.$lesson->id.'/licao.pdf', '%PDF-1.4 teste');

        return LessonMaterial::factory()->for($lesson)
            ->withFile('materials/'.$lesson->id.'/licao.pdf', disk: 'local')
            ->create(['original_name' => 'licao.pdf']);
    }

    public function test_pdf_of_public_lesson_opens_inline_for_guests(): void
    {
        $material = $this->materialFor(Lesson::factory()->published()->create());

        $response = $this->get(route('materials.file', $material));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_download_parameter_forces_attachment(): void
    {
        $material = $this->materialFor(Lesson::factory()->published()->create());

        $response = $this->get(route('materials.file', [$material, 'download' => 1]));

        $this->assertStringStartsWith('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_files_of_draft_lessons_are_not_exposed(): void
    {
        $material = $this->materialFor(Lesson::factory()->create());

        $this->get(route('materials.file', $material))->assertNotFound();
    }

    public function test_files_of_members_only_lessons_require_membership(): void
    {
        $classroom = Classroom::factory()->create();
        $material = $this->materialFor(Lesson::factory()->for($classroom)->published()->membersOnly()->create());

        $this->get(route('materials.file', $material))->assertNotFound();

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('materials.file', $material))->assertNotFound();

        $member = User::factory()->studentOf($classroom)->create();
        $this->actingAs($member)->get(route('materials.file', $material))->assertOk();
    }
}
