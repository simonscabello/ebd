<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;

class ClassroomPolicy
{
    /** Lista de classes: cada um vê as que gerencia (a administração, as da igreja). */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    /** Cadastro de classes é tarefa da administração da igreja. */
    public function create(User $user): bool
    {
        return $user->administersAnyChurch();
    }

    public function update(User $user, Classroom $classroom): bool
    {
        return in_array($classroom->church_id, $user->administeredChurchIds(), true);
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

    /** Definir quem é professor(a) é restrito à administração da igreja. */
    public function assignTeachers(User $user, Classroom $classroom): bool
    {
        return in_array($classroom->church_id, $user->administeredChurchIds(), true);
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
