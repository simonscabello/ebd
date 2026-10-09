<?php

use App\Support\ChurchCalendar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migra o modelo "lição = domingo" para "lição x encontros":
 *
 * - cada lição com data vira um encontro (realizado se concluída ou no passado);
 * - "concluída" deixa de ser status: o status passa a ser só editorial
 *   (rascunho/publicada) e "já foi dada" vem dos encontros;
 * - notas do professor viram um bloco de conteúdo exclusivo do professor.
 *
 * Nenhuma presença é registrada retroativamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $today = ChurchCalendar::today()->toDateString();

            // Uma lição por classe e data (o encontro é único por dia). Em caso de
            // colisão, prefere a concluída, depois a publicada.
            DB::statement(<<<'SQL'
                INSERT INTO class_meetings (classroom_id, lesson_id, held_on, status, created_at, updated_at)
                SELECT DISTINCT ON (classroom_id, scheduled_for)
                    classroom_id,
                    id,
                    scheduled_for,
                    CASE WHEN status = 'completed' OR scheduled_for < ? THEN 'held' ELSE 'planned' END,
                    NOW(),
                    NOW()
                FROM lessons
                WHERE scheduled_for IS NOT NULL AND deleted_at IS NULL
                ORDER BY classroom_id, scheduled_for, (status = 'completed') DESC, (status = 'published') DESC, id
                SQL, [$today]);

            DB::table('lessons')->where('status', 'completed')->update(['status' => 'published']);

            DB::statement('ALTER TABLE lessons DROP CONSTRAINT lessons_status_check');
            DB::statement('ALTER TABLE lessons DROP CONSTRAINT lessons_published_requires_date_check');
            DB::statement("ALTER TABLE lessons ADD CONSTRAINT lessons_status_check CHECK (status IN ('draft', 'published'))");

            DB::statement(<<<'SQL'
                INSERT INTO lesson_blocks (lesson_id, kind, audience, title, body, position, created_at, updated_at)
                SELECT id, 'teacher_note', 'teacher', 'Notas do professor', teacher_notes, 1, NOW(), NOW()
                FROM lessons
                WHERE teacher_notes IS NOT NULL AND btrim(teacher_notes) <> ''
                SQL);
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['classroom_id', 'status', 'scheduled_for']);
            $table->dropColumn(['teacher_notes', 'completed_at']);
            $table->index(['classroom_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['classroom_id', 'status']);
            $table->text('teacher_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->index(['classroom_id', 'status', 'scheduled_for']);
        });

        DB::transaction(function () {
            DB::statement(<<<'SQL'
                UPDATE lessons l SET teacher_notes = b.body
                FROM (
                    SELECT DISTINCT ON (lesson_id) lesson_id, body
                    FROM lesson_blocks WHERE kind = 'teacher_note'
                    ORDER BY lesson_id, position, id
                ) b
                WHERE b.lesson_id = l.id
                SQL);
            DB::table('lesson_blocks')->where('kind', 'teacher_note')->delete();

            DB::statement('ALTER TABLE lessons DROP CONSTRAINT lessons_status_check');
            DB::statement("ALTER TABLE lessons ADD CONSTRAINT lessons_status_check CHECK (status IN ('draft', 'published', 'completed'))");

            // Publicada cujos encontros já foram todos realizados volta a ser "concluída".
            DB::statement(<<<'SQL'
                UPDATE lessons l SET status = 'completed', completed_at = NOW()
                WHERE l.status = 'published'
                  AND EXISTS (SELECT 1 FROM class_meetings m WHERE m.lesson_id = l.id)
                  AND NOT EXISTS (SELECT 1 FROM class_meetings m WHERE m.lesson_id = l.id AND m.status <> 'held')
                SQL);

            DB::statement("UPDATE lessons SET status = 'draft' WHERE status <> 'draft' AND scheduled_for IS NULL");
            DB::statement("ALTER TABLE lessons ADD CONSTRAINT lessons_published_requires_date_check CHECK (status = 'draft' OR scheduled_for IS NOT NULL)");

            DB::table('class_meetings')->delete();
        });
    }
};
