<?php

namespace App\Queries;

use App\Models\ClassMeeting;
use App\Models\Lesson;
use App\Support\ChurchCalendar;

/**
 * Qual encontro o Modo Domingo está conduzindo: o de hoje; senão o último já
 * passado ainda sem chamada; senão o próximo.
 */
class MeetingForLessonQuery
{
    public function for(Lesson $lesson): ?ClassMeeting
    {
        $today = ChurchCalendar::today()->toDateString();
        $base = fn () => ClassMeeting::query()->where('lesson_id', $lesson->id)->active();

        return $base()->whereDate('held_on', $today)->first()
            ?? $base()->whereDate('held_on', '<', $today)->whereNull('attendance_taken_at')->orderByDesc('held_on')->first()
            ?? $base()->whereDate('held_on', '>', $today)->chronological()->first();
    }
}
