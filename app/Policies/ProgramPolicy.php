<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    /** Programas são assunto da administração da igreja. */
    public function viewAny(User $user): bool
    {
        $church = $user->currentChurch();

        return $church !== null && $user->isChurchAdminOf($church);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Program $program): bool
    {
        return in_array($program->church_id, $user->administeredChurchIds(), true);
    }

    /** Quem nomeia professores nomeia coordenadores: a administração da igreja. */
    public function appointCoordinators(User $user, Program $program): bool
    {
        return in_array($program->church_id, $user->administeredChurchIds(), true);
    }
}
