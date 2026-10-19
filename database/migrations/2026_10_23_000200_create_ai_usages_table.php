<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uso da IA por igreja (uma linha por pergunta respondida e por áudio
 * gerado): base dos limites e dos números do painel do dono. Não guarda
 * conteúdo. O histórico vem das perguntas e dos áudios que já existem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 16);
            $table->timestamp('created_at');

            $table->index(['church_id', 'kind', 'created_at']);
            $table->index('lesson_id');
        });

        DB::statement("ALTER TABLE ai_usages ADD CONSTRAINT ai_usages_kind_check CHECK (kind IN ('question', 'audio'))");

        DB::statement(<<<'SQL'
            INSERT INTO ai_usages (church_id, lesson_id, kind, created_at)
            SELECT c.church_id, q.lesson_id, 'question', q.created_at
            FROM study_questions q
            JOIN lessons l ON l.id = q.lesson_id
            JOIN classrooms c ON c.id = l.classroom_id
            SQL);

        DB::statement(<<<'SQL'
            INSERT INTO ai_usages (church_id, lesson_id, kind, created_at)
            SELECT c.church_id, l.id, 'audio', l.audio_generated_at
            FROM lessons l
            JOIN classrooms c ON c.id = l.classroom_id
            WHERE l.audio_generated_at IS NOT NULL
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
    }
};
