<?php

namespace App\Queries;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;

/**
 * O cartão do topo das páginas da classe: série atual, em que lição da série
 * a classe está e quantos alunos tem.
 */
class ClassroomHeaderQuery
{
    /**
     * @return array{current_series: array{id: int, title: string}|null, lesson_position: array{index: int, total: int}|null, students: int, teachers: list<string>, can_edit: bool, can_manage: bool}
     */
    public function for(Classroom $classroom, User $user): array
    {
        $series = $classroom->currentSeries;
        $sequence = $series === null ? [] : Lesson::query()->whereBelongsTo($series)->inSequence()->pluck('id')->all();
        $position = array_search($classroom->current_lesson_id, $sequence, true);

        return [
            'current_series' => $series === null ? null : ['id' => $series->id, 'title' => $series->title],
            'lesson_position' => is_int($position) ? ['index' => $position + 1, 'total' => count($sequence)] : null,
            'students' => $classroom->students()->count(),
            'teachers' => array_values($classroom->teachers()->orderBy('name')->pluck('name')->all()),
            'can_edit' => $user->can('update', $classroom),
            'can_manage' => $user->can('manageContent', $classroom),
        ];
    }
}
