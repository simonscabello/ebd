<?php

namespace App\Actions\Lessons;

use App\Models\Classroom;
use Illuminate\Support\Facades\DB;

/**
 * lessons.scheduled_for é um cache da data do primeiro encontro (não cancelado)
 * da lição. Serve à biblioteca (ano, ordenação) e às listagens; a fonte da
 * verdade são os encontros (class_meetings).
 */
class SyncLessonSchedule
{
    public function handle(Classroom|int $classroom): void
    {
        $classroomId = $classroom instanceof Classroom ? $classroom->id : $classroom;

        DB::update(<<<'SQL'
            UPDATE lessons SET scheduled_for = (
                SELECT MIN(m.held_on) FROM class_meetings m
                WHERE m.lesson_id = lessons.id AND m.status <> 'cancelled'
            )
            WHERE classroom_id = ?
            SQL, [$classroomId]);
    }
}
