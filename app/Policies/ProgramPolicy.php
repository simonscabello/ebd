<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    /** Quem nomeia professores nomeia coordenadores: a administração da igreja. */
    public function appointCoordinators(User $user, Program $program): bool
    {
        return in_array($program->church_id, $user->administeredChurchIds(), true);
    }
}
