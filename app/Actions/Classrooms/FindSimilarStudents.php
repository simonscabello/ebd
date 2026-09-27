<?php

namespace App\Actions\Classrooms;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Alunos da classe com nome parecido com o que está sendo cadastrado (sem
 * acento, maiúsculas ou espaços extras; um nome contido no outro). Evita
 * cadastrar a mesma pessoa duas vezes.
 */
class FindSimilarStudents
{
    /**
     * @return Collection<int, User>
     */
    public function in(Classroom $classroom, string $name): Collection
    {
        $needle = $this->normalize($name);

        return $classroom->students()->orderBy('name')->get()
            ->filter(function (User $student) use ($needle) {
                $existing = $this->normalize($student->name);

                return str_contains($existing, $needle) || str_contains($needle, $existing);
            })
            ->values();
    }

    private function normalize(string $name): string
    {
        return Str::lower(Str::ascii(Str::squish($name)));
    }
}
