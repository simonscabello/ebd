<?php

namespace App\Queries;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use App\Support\ChurchCalendar;
use Illuminate\Database\Eloquent\Collection;

/**
 * "O que eu preciso estudar para o próximo domingo?"
 */
class NextLessonQuery
{
    /**
     * Próxima lição publicada (data de hoje em diante) visível para a pessoa.
     */
    public function next(Classroom $classroom, ?User $user): ?Lesson
    {
        return Lesson::query()
            ->visibleTo($user)
            ->whereBelongsTo($classroom)
            ->upcoming(ChurchCalendar::today())
            ->with(['series', 'classroom', 'materials', 'readings', 'questions'])
            ->first();
    }

    /**
     * Últimas aulas que já aconteceram, para acesso rápido na home.
     *
     * @return Collection<int, Lesson>
     */
    public function recent(Classroom $classroom, ?User $user, int $limit = 3)
    {
        return Lesson::query()
            ->visibleTo($user)
            ->whereBelongsTo($classroom)
            ->whereDate('scheduled_for', '<', ChurchCalendar::today()->toDateString())
            ->with(['series', 'classroom'])
            ->orderByDesc('scheduled_for')
            ->limit($limit)
            ->get();
    }
}
