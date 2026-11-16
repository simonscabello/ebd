<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sequências de leitura: uma lição que dura mais de um encontro tem um plano
 * por semana (ver Lesson::readingsForWeek). Tudo o que existe é a semana 1.
 * A IA que escreve a sequência que falta registra o uso como "readings".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_readings', function (Blueprint $table) {
            $table->unsignedTinyInteger('week')->default(1)->after('lesson_id');
        });

        DB::statement('ALTER TABLE ai_usages DROP CONSTRAINT ai_usages_kind_check');
        DB::statement("ALTER TABLE ai_usages ADD CONSTRAINT ai_usages_kind_check CHECK (kind IN ('question', 'audio', 'readings'))");
    }

    public function down(): void
    {
        DB::table('ai_usages')->where('kind', 'readings')->delete();
        DB::statement('ALTER TABLE ai_usages DROP CONSTRAINT ai_usages_kind_check');
        DB::statement("ALTER TABLE ai_usages ADD CONSTRAINT ai_usages_kind_check CHECK (kind IN ('question', 'audio'))");

        Schema::table('lesson_readings', function (Blueprint $table) {
            $table->dropColumn('week');
        });
    }
};
