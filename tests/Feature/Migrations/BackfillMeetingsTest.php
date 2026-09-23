<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A migração que separa "lição" de "domingo" precisa preservar os dados de produção.
 */
class BackfillMeetingsTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_09_23_001000_backfill_meetings_and_simplify_lesson_status.php';

    public function test_old_lessons_become_meetings_and_teacher_notes_become_blocks(): void
    {
        config(['ebd.timezone' => 'Europe/Madrid']);
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 23)->setTime(10, 0));

        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('lessons', 'teacher_notes'));

        $classroom = DB::table('classrooms')->insertGetId(['name' => 'Adultos', 'slug' => 'adultos', 'created_at' => now(), 'updated_at' => now()]);
        $lesson = fn (array $data) => DB::table('lessons')->insertGetId([
            'classroom_id' => $classroom,
            'title' => $data['slug'],
            'created_at' => now(),
            'updated_at' => now(),
            ...$data,
        ]);

        $done = $lesson(['slug' => 'concluida', 'status' => 'completed', 'scheduled_for' => '2026-09-13', 'completed_at' => now(), 'teacher_notes' => '- Abrir com oração']);
        $past = $lesson(['slug' => 'passada', 'status' => 'published', 'scheduled_for' => '2026-09-20', 'teacher_notes' => '   ']);
        $next = $lesson(['slug' => 'proxima', 'status' => 'published', 'scheduled_for' => '2026-09-27']);
        $clash = $lesson(['slug' => 'rascunho-mesmo-dia', 'status' => 'draft', 'scheduled_for' => '2026-09-27']);
        $undated = $lesson(['slug' => 'sem-data', 'status' => 'draft']);

        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();

        $meetings = DB::table('class_meetings')->orderBy('held_on')->get(['lesson_id', 'held_on', 'status'])->map(fn ($m) => (array) $m)->all();
        $this->assertSame([
            ['lesson_id' => $done, 'held_on' => '2026-09-13', 'status' => 'held'],
            ['lesson_id' => $past, 'held_on' => '2026-09-20', 'status' => 'held'],
            ['lesson_id' => $next, 'held_on' => '2026-09-27', 'status' => 'planned'],
        ], $meetings);

        $this->assertSame('published', DB::table('lessons')->where('id', $done)->value('status'));
        $this->assertFalse(Schema::hasColumn('lessons', 'teacher_notes'));
        $this->assertFalse(Schema::hasColumn('lessons', 'completed_at'));
        $this->assertSame(0, DB::table('class_meetings')->whereIn('lesson_id', [$clash, $undated])->count());
        $this->assertSame('- Abrir com oração', DB::table('lesson_blocks')->where('lesson_id', $done)->where('kind', 'teacher_note')->where('audience', 'teacher')->value('body'));
        $this->assertSame(1, DB::table('lesson_blocks')->count());
    }
}
