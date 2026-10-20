<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Numa lição que continua, cada semana de leitura tem a sua marcação do
        // mesmo dia do plano (ver App\Support\ReadingWeeks). Os registros que já
        // existem continuam valendo: o índice novo é mais largo que o antigo.
        Schema::table('reading_checkins', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'lesson_id', 'weekday']);
            $table->unique(['user_id', 'lesson_id', 'weekday', 'read_on']);
        });
    }

    public function down(): void
    {
        Schema::table('reading_checkins', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'lesson_id', 'weekday', 'read_on']);
        });

        // Volta a um registro por dia do plano: fica o mais antigo.
        DB::statement(<<<'SQL'
            DELETE FROM reading_checkins rc
            USING reading_checkins older
            WHERE rc.user_id = older.user_id
              AND rc.lesson_id = older.lesson_id
              AND rc.weekday = older.weekday
              AND rc.id > older.id
        SQL);

        Schema::table('reading_checkins', function (Blueprint $table) {
            $table->unique(['user_id', 'lesson_id', 'weekday']);
        });
    }
};
