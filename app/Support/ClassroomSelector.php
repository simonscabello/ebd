<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Escolha da classe em telas com seletor (?classe=slug): home e "Minha semana".
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
        $classrooms = Classroom::query()->active()->ordered()->get();

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
        if (is_string($slug) && ($found = $classrooms->firstWhere('slug', $slug))) {
            return $found;
        }

        return $classrooms->first();
    }
}
