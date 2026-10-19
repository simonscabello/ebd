<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dia da semana do encontro da turma (ISO: 1 = segunda ... 7 = domingo).
 * As turmas que já existem continuam no domingo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->unsignedSmallInteger('meeting_weekday')->default(7)->after('description');
        });

        DB::statement('ALTER TABLE classrooms ADD CONSTRAINT classrooms_meeting_weekday_check CHECK (meeting_weekday BETWEEN 1 AND 7)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE classrooms DROP CONSTRAINT IF EXISTS classrooms_meeting_weekday_check');

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('meeting_weekday');
        });
    }
};
