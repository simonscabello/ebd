<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A biblioteca oficial não se reúne: apaga os domingos que o preenchimento
 * automático criou para a turma dela (sem presença nem visitantes). Outras
 * turmas não são tocadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $classrooms = DB::table('classrooms')
            ->whereIn('church_id', DB::table('churches')->where('is_official', true)->select('id'))
            ->select('id');

        $meetings = DB::table('class_meetings')
            ->whereIn('classroom_id', $classrooms)
            ->whereNotExists(fn ($query) => $query->from('attendances')->whereColumn('attendances.class_meeting_id', 'class_meetings.id'));

        $meetings->delete();
    }

    public function down(): void
    {
        // Nada a restaurar: os encontros eram gerados automaticamente.
    }
};
