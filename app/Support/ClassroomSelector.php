<?php

namespace App\Support;

use App\Models\Church;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Escolha da classe em telas com seletor (?classe=slug): Início e "Leituras da semana".
 */
class ClassroomSelector
{
    /**
     * Classes ativas. Membros veem primeiro as próprias classes.
     *
     * @return Collection<int, Classroom>
     */
    public function available(?User $user, bool $onlyMine = false): Collection
    {
        $classrooms = Classroom::query()->active()->whereIn('church_id', Church::idsVisibleTo($user))->ordered()->get();

        if ($user === null) {
            return $onlyMine ? collect() : $classrooms;
        }

        $mine = $user->memberClassroomIds();

        if ($onlyMine) {
            return $classrooms->filter(fn (Classroom $c) => in_array($c->id, $mine, true))->values();
        }

        return $classrooms
            ->sortBy(fn (Classroom $c) => in_array($c->id, $mine, true) ? 0 : 1)
            ->values();
    }

    /**
     * @param  Collection<int, Classroom>  $classrooms
     */
    public function selected(Collection $classrooms, mixed $slug): ?Classroom
    {
        $classroom = is_string($slug) ? $classrooms->firstWhere('slug', $slug) : null;
        $classroom ??= $classrooms->first();

        // A página passa a ver "hoje" no fuso da igreja da classe escolhida
        // (o middleware UseChurchTimezone restaura no fim da requisição).
        if ($classroom !== null) {
            ChurchCalendar::setTimezone($classroom->church);
        }

        return $classroom;
    }
}
