<?php

namespace App\Support;

use App\Enums\ClassroomRole;
use Illuminate\Support\Facades\DB;

/**
 * A partir de quando cada aluno conta nos números da classe.
 *
 * O início é o menor entre o dia seguinte à entrada na classe (no fuso da
 * igreja) e a primeira presença registrada nela. Quem é cadastrado durante a
 * aula de domingo só conta naquele domingo se estiver na chamada; uma chamada
 * corrigida depois, com presença antes da entrada, antecipa o início.
 */
class Enrollment
{
    /**
     * @param  list<int>|null  $userIds  null = todos os alunos da classe
     * @return array<int, string> id do aluno => Y-m-d
     */
    public static function sinceMap(int $classroomId, ?array $userIds = null): array
    {
        $firstPresence = DB::table('attendances')
            ->join('class_meetings', 'class_meetings.id', '=', 'attendances.class_meeting_id')
            ->where('class_meetings.classroom_id', $classroomId)
            ->groupBy('attendances.user_id')
            ->selectRaw('attendances.user_id, MIN(class_meetings.held_on) AS first_present');

        return DB::table('classroom_user')
            ->leftJoinSub($firstPresence, 'first_presence', 'first_presence.user_id', '=', 'classroom_user.user_id')
            ->where('classroom_user.classroom_id', $classroomId)
            ->where('classroom_user.role', ClassroomRole::Student->value)
            ->when($userIds !== null, fn ($query) => $query->whereIn('classroom_user.user_id', $userIds))
            ->selectRaw('classroom_user.user_id, LEAST('.ChurchCalendar::JOINED_ON_SQL.' + 1, first_presence.first_present) AS since', [ChurchCalendar::timezone()])
            ->pluck('since', 'user_id')
            ->mapWithKeys(fn ($date, $id) => [(int) $id => substr((string) $date, 0, 10)])
            ->all();
    }
}
