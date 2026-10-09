<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Blocos de conteúdo além do estudo principal: roteiro, contexto histórico,
        // teologia, curiosidades, conceitos... "audience" separa o que é só do professor.
        Schema::create('lesson_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('audience', 20);
            $table->string('title', 180)->nullable();
            $table->text('body');
            // Dia da semana (ISO) em que o bloco aparece em "Minha semana".
            $table->unsignedSmallInteger('drip_weekday')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['lesson_id', 'position']);
        });

        DB::statement("ALTER TABLE lesson_blocks ADD CONSTRAINT lesson_blocks_kind_check CHECK (kind IN ('roteiro', 'extra_time', 'accuracy_note', 'teacher_note', 'context', 'theology', 'curiosity', 'application', 'concept'))");
        DB::statement("ALTER TABLE lesson_blocks ADD CONSTRAINT lesson_blocks_audience_check CHECK (audience IN ('teacher', 'student'))");
        DB::statement('ALTER TABLE lesson_blocks ADD CONSTRAINT lesson_blocks_drip_weekday_check CHECK (drip_weekday IS NULL OR drip_weekday BETWEEN 1 AND 7)');
        DB::statement("ALTER TABLE lesson_blocks ADD CONSTRAINT lesson_blocks_drip_audience_check CHECK (audience = 'student' OR drip_weekday IS NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_blocks');
    }
};
