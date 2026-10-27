<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A classe passa a ter uma lição atual: ela fica quantos domingos precisar e
 * só sai quando o professor conclui. Os encontros dizem quando a lição
 * aconteceu, não qual é a próxima.
 *
 * - classrooms.current_lesson_id / current_series_id: o que a classe estuda agora
 *   (série atual pode existir sem lição pronta).
 * - classrooms.current_lesson_started_at: "em estudo desde".
 * - series.concluded_at: série marcada como concluída pelo professor.
 * - lessons.studied_at: lição concluída ("Estudada").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreignId('current_series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->foreignId('current_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->timestamp('current_lesson_started_at')->nullable();
        });

        Schema::table('series', function (Blueprint $table) {
            $table->timestamp('concluded_at')->nullable();
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->timestamp('studied_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('studied_at');
        });

        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn('concluded_at');
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_lesson_id');
            $table->dropConstrainedForeignId('current_series_id');
            $table->dropColumn('current_lesson_started_at');
        });
    }
};
