<?php

namespace Tests\Feature\Mcp;

use App\Enums\LessonStatus;
use App\Events\LessonPublished;
use App\Mcp\Servers\EbdServer;
use App\Mcp\Tools\RemoveLessonItem;
use App\Mcp\Tools\SaveLessonBlockTool;
use App\Mcp\Tools\SaveLessonDraft;
use App\Mcp\Tools\SaveLessonMaterial;
use App\Mcp\Tools\SaveLessonReading;
use App\Models\AuditLog;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\LessonMaterial;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Lições pelo servidor MCP: o agente cria e edita rascunhos, nunca publica,
 * e cada alteração fica no histórico com o antes e o depois.
 */
class McpLessonToolsTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private Series $series;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'Europe/Madrid']);
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 24)->setTime(10, 0));
        Event::fake([LessonPublished::class]);

        $this->classroom = Classroom::factory()->create(['slug' => 'jovens']);
        $this->series = Series::factory()->for($this->classroom)->create(['slug' => '4-tri-2026']);
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
    }

    protected function tearDown(): void
    {
        Event::assertNotDispatched(LessonPublished::class);

        parent::tearDown();
    }

    public function test_creates_a_complete_draft_and_schedules_it(): void
    {
        EbdServer::actingAs($this->teacher)->tool(SaveLessonDraft::class, [
            'classroom' => 'jovens',
            'series' => '4-tri-2026',
            'meeting_on' => '2026-10-04',
            'number' => 1,
            'title' => 'O chamado de Abraão',
            'content' => "## Deus chama\nTexto",
            'readings' => [['weekday' => 1, 'reference' => 'Gênesis 12.1-9']],
            'blocks' => [['kind' => 'roteiro', 'body' => 'Abrir com oração', 'drip_weekday' => 3]],
            'materials' => [['type' => 'video', 'title' => 'Vídeo', 'url' => 'https://youtu.be/abc']],
            'status' => 'published',
        ])->assertOk()->assertSee(['"saved":"created"', '"status":"draft"', 'Abrir com oração']);

        $lesson = Lesson::query()->sole();
        $this->assertSame(LessonStatus::Draft, $lesson->status);
        $this->assertSame($this->series->id, $lesson->series_id);
        $this->assertSame([$this->teacher->id], $lesson->authors()->pluck('users.id')->all());
        $this->assertSame(1, $lesson->readings()->count());
        $this->assertNull($lesson->blocks()->sole()->drip_weekday, 'roteiro é do professor: nunca vai para "Minha semana"');
        $this->assertSame('2026-10-04', ClassMeeting::query()->where('lesson_id', $lesson->id)->sole()->held_on->toDateString());

        $log = AuditLog::query()->sole();
        $this->assertSame(['save_lesson_draft', 'ok', $this->teacher->id], [$log->tool, $log->status, $log->user_id]);
        $this->assertSame($lesson->id, $log->subject_id);
        $this->assertSame('O chamado de Abraão', $log->changes['after']['title']);
    }

    public function test_edits_only_the_fields_sent_and_replaces_lists_sent(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->create(['title' => 'Antigo', 'goal' => 'Alvo que fica']);
        $lesson->readings()->create(['reference' => 'Salmos 1']);
        $block = LessonBlock::factory()->for($lesson)->create();

        EbdServer::actingAs($this->teacher)->tool(SaveLessonDraft::class, [
            'lesson_id' => $lesson->id,
            'title' => 'Novo título',
            'readings' => [['weekday' => 2, 'reference' => 'Salmos 23']],
        ])->assertOk();

        $lesson->refresh();
        $this->assertSame(['Novo título', 'Alvo que fica'], [$lesson->title, $lesson->goal]);
        $this->assertSame(['Salmos 23'], $lesson->readings()->pluck('reference')->all());
        $this->assertTrue($block->exists && $block->fresh() !== null, 'blocos não enviados continuam');

        $changes = AuditLog::query()->sole()->changes;
        $this->assertSame('Antigo', $changes['before']['title']);
        $this->assertSame('Novo título', $changes['after']['title']);
        $this->assertArrayNotHasKey('goal', $changes['after']);
    }

    public function test_published_lessons_and_other_classrooms_are_off_limits(): void
    {
        $published = Lesson::factory()->for($this->classroom)->published()->create();
        $foreign = Lesson::factory()->for(Classroom::factory())->create();

        EbdServer::actingAs($this->teacher)->tool(SaveLessonDraft::class, ['lesson_id' => $published->id, 'title' => 'x'])
            ->assertHasErrors(['já foi publicada', "/admin/licoes/{$published->id}/editar"]);

        EbdServer::actingAs($this->teacher)->tool(SaveLessonBlockTool::class, ['lesson_id' => $published->id, 'kind' => 'curiosity', 'body' => 'x'])
            ->assertHasErrors(['já foi publicada']);

        EbdServer::actingAs($this->teacher)->tool(SaveLessonDraft::class, ['lesson_id' => $foreign->id, 'title' => 'x'])
            ->assertHasErrors(['não encontrada nas classes que você gerencia']);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_number_is_unique_within_the_series(): void
    {
        $taken = Lesson::factory()->for($this->classroom)->forSeries($this->series)->number(3)->create();

        EbdServer::actingAs($this->teacher)->tool(SaveLessonDraft::class, ['classroom' => 'jovens', 'series' => '4-tri-2026', 'number' => 3, 'title' => 'Outra'])
            ->assertHasErrors(["(id {$taken->id}) nesta série"]);
    }

    public function test_validation_messages_are_in_portuguese(): void
    {
        EbdServer::actingAs($this->teacher)->tool(SaveLessonDraft::class, ['classroom' => 'jovens'])
            ->assertHasErrors(['título']);
    }

    public function test_block_reading_and_material_items_can_be_added_edited_and_removed(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->create();

        EbdServer::actingAs($this->teacher)->tool(SaveLessonBlockTool::class, ['lesson_id' => $lesson->id, 'kind' => 'curiosity', 'title' => 'Ur', 'body' => 'Cidade antiga'])
            ->assertOk()->assertSee('"audience":"student"');
        $block = $lesson->blocks()->sole();

        EbdServer::actingAs($this->teacher)->tool(SaveLessonBlockTool::class, ['lesson_id' => $lesson->id, 'block_id' => $block->id, 'title' => 'Ur dos caldeus'])
            ->assertOk();
        $this->assertSame(['Ur dos caldeus', 'Cidade antiga'], [$block->fresh()->title, $block->fresh()->body]);
        $this->assertStringContainsString('Cidade antiga', (string) $lesson->fresh()->blocks_text);

        EbdServer::actingAs($this->teacher)->tool(SaveLessonReading::class, ['lesson_id' => $lesson->id, 'weekday' => 1, 'reference' => 'Atos 7.2-4'])
            ->assertOk()->assertSee('Atos 7.2-4');

        EbdServer::actingAs($this->teacher)->tool(SaveLessonMaterial::class, ['lesson_id' => $lesson->id, 'type' => 'pdf', 'title' => 'Revista'])
            ->assertHasErrors();
        EbdServer::actingAs($this->teacher)->tool(SaveLessonMaterial::class, ['lesson_id' => $lesson->id, 'type' => 'link', 'title' => 'Mapa'])
            ->assertHasErrors(['link']);
        EbdServer::actingAs($this->teacher)->tool(SaveLessonMaterial::class, ['lesson_id' => $lesson->id, 'type' => 'link', 'title' => 'Mapa', 'url' => 'https://exemplo.com/mapa'])
            ->assertOk();

        EbdServer::actingAs($this->teacher)->tool(RemoveLessonItem::class, ['lesson_id' => $lesson->id, 'type' => 'block', 'id' => $block->id])
            ->assertOk();
        $this->assertSame(0, $lesson->blocks()->count());

        $this->assertSame(
            ['save_lesson_block', 'save_lesson_block', 'save_lesson_reading', 'save_lesson_material', 'remove_lesson_item'],
            AuditLog::query()->orderBy('id')->pluck('tool')->all(),
        );
    }

    public function test_file_materials_keep_their_file_and_cannot_be_removed(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->create();
        $pdf = LessonMaterial::factory()->for($lesson)->withFile('materiais/revista.pdf')->create(['type' => 'pdf', 'title' => 'Revista', 'is_primary' => true]);

        EbdServer::actingAs($this->teacher)->tool(SaveLessonMaterial::class, ['lesson_id' => $lesson->id, 'material_id' => $pdf->id, 'title' => 'Revista do aluno'])
            ->assertOk();
        $this->assertSame(['Revista do aluno', 'materiais/revista.pdf', true], [$pdf->fresh()->title, $pdf->fresh()->path, $pdf->fresh()->is_primary]);

        EbdServer::actingAs($this->teacher)->tool(RemoveLessonItem::class, ['lesson_id' => $lesson->id, 'type' => 'material', 'id' => $pdf->id])
            ->assertHasErrors(['só pode ser removido pelo app']);
        $this->assertNotNull($pdf->fresh());
    }

    public function test_the_mcp_layer_never_publishes_or_force_deletes(): void
    {
        $offending = collect(glob(app_path('Mcp').'/**/*.php') ?: [])
            ->merge(glob(app_path('Mcp').'/*.php') ?: [])
            ->filter(fn (string $file) => preg_match('/ChangeLessonStatus|forceDelete|LessonStatus::Published/', (string) file_get_contents($file)) === 1)
            ->map(fn (string $file) => basename($file))
            ->values()
            ->all();

        $this->assertSame([], $offending);
    }
}
