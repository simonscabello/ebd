<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A leitura passa a ser marcada pelo dia do plano (1 = segunda ... 7 =
        // domingo), em qualquer momento. read_on continua sendo a data em que a
        // pessoa marcou (usada na sequência de dias e em "estudando agora").
        Schema::table('reading_checkins', function (Blueprint $table) {
            $table->unsignedSmallInteger('weekday')->nullable()->after('lesson_reading_id');
        });

        DB::statement(<<<'SQL'
            UPDATE reading_checkins rc
            SET weekday = COALESCE(
                (SELECT lr.weekday FROM lesson_readings lr WHERE lr.id = rc.lesson_reading_id),
                EXTRACT(ISODOW FROM rc.read_on)::smallint
            )
        SQL);

        // Uma lição que durou mais de uma semana pode ter dois registros no mesmo dia do plano.
        DB::statement(<<<'SQL'
            DELETE FROM reading_checkins rc
            USING reading_checkins older
            WHERE rc.user_id = older.user_id
              AND rc.lesson_id = older.lesson_id
              AND rc.weekday = older.weekday
              AND rc.id > older.id
        SQL);

        Schema::table('reading_checkins', function (Blueprint $table) {
            $table->unsignedSmallInteger('weekday')->nullable(false)->change();
            $table->dropUnique(['user_id', 'lesson_id', 'read_on']);
            $table->unique(['user_id', 'lesson_id', 'weekday']);
        });

        DB::statement('ALTER TABLE reading_checkins ADD CONSTRAINT reading_checkins_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reading_checkins DROP CONSTRAINT reading_checkins_weekday_check');

        // Volta a um registro por data.
        DB::statement(<<<'SQL'
            DELETE FROM reading_checkins rc
            USING reading_checkins older
            WHERE rc.user_id = older.user_id
              AND rc.lesson_id = older.lesson_id
              AND rc.read_on = older.read_on
              AND rc.id > older.id
        SQL);

        Schema::table('reading_checkins', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'lesson_id', 'weekday']);
            $table->unique(['user_id', 'lesson_id', 'read_on']);
            $table->dropColumn('weekday');
        });
    }
};
