<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;

class ClassroomPolicy
{
    /** Cadastro de classes é tarefa da administração geral. */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Classroom $classroom): bool
    {
        return $user->isAdmin();
    }

    /** Criar séries e lições na classe. */
    public function manageContent(User $user, Classroom $classroom): bool
    {
        return $user->canManageClassroom($classroom);
    }

    /** Ver e adicionar alunos. */
    public function manageMembers(User $user, Classroom $classroom): bool
    {
        return $user->canManageClassroom($classroom);
    }

    /** Definir quem é professor(a) é restrito à administração. */
    public function assignTeachers(User $user, Classroom $classroom): bool
    {
        return $user->isAdmin();
    }
}
