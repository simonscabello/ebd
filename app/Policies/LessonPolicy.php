<?php

namespace App\Policies;

use App\Enums\LessonVisibility;
use App\Models\Lesson;
use App\Models\User;

/**
 * Regras de acesso às lições:
 *
 * - Rascunho: apenas quem gerencia a classe (professores dela e administradores).
 * - Publicada + pública: qualquer pessoa, inclusive sem login.
 * - Publicada + membros: pessoas autenticadas vinculadas à classe.
 *
 * O escopo Lesson::visibleTo() aplica a mesma regra em consultas.
 */
class LessonPolicy
{
    public function view(?User $user, Lesson $lesson): bool
    {
        if ($user?->canManageClassroom($lesson->classroom_id)) {
            return true;
        }

        if (! $lesson->status->isVisible()) {
            return false;
        }

        if ($lesson->visibility === LessonVisibility::Public) {
            return true;
        }

        return $user !== null && $user->isMemberOf($lesson->classroom_id);
    }

    /**
     * Conteúdo do professor (roteiro, notas de precisão, materiais só do
     * professor, notas e chamada dos encontros) nunca sai para alunos ou visitantes.
     */
    public function viewTeacherContent(?User $user, Lesson $lesson): bool
    {
        return $user?->canManageClassroom($lesson->classroom_id) ?? false;
    }

    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->canManageClassroom($lesson->classroom_id);
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $user->canManageClassroom($lesson->classroom_id);
    }
}
