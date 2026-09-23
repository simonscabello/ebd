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

    /** Painel de evolução da classe. */
    public function viewInsights(User $user, Classroom $classroom): bool
    {
        return $user->canManageClassroom($classroom);
    }

    /**
     * Progresso de um aluno: só de quem é aluno desta classe. Para os demais,
     * 404 (não confirma que a pessoa existe).
     */
    public function viewStudentProgress(User $user, Classroom $classroom, User $student): bool
    {
        if (! $user->canManageClassroom($classroom)) {
            return false;
        }

        if (! $student->isMemberOf($classroom) || $student->isTeacherOf($classroom)) {
            abort(404);
        }

        return true;
    }
}
