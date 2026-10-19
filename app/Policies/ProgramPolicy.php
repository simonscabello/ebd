<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    /** Quem nomeia professores nomeia coordenadores (hoje, a administração). */
    public function appointCoordinators(User $user, Program $program): bool
    {
        return $user->isAdmin();
    }
}
