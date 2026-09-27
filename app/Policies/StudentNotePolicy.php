<?php

namespace App\Policies;

use App\Models\StudentNote;
use App\Models\User;

/**
 * Anotações sobre alunos: quem escreveu edita e apaga; a administração também.
 */
class StudentNotePolicy
{
    public function update(User $user, StudentNote $note): bool
    {
        return $user->canManageClassroom($note->classroom_id)
            && ($user->isAdmin() || $note->author_id === $user->id);
    }

    public function delete(User $user, StudentNote $note): bool
    {
        return $this->update($user, $note);
    }
}
