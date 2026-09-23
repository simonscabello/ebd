<?php

namespace App\Policies;

use App\Models\Series;
use App\Models\User;

class SeriesPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function update(User $user, Series $series): bool
    {
        return $user->canManageClassroom($series->classroom_id);
    }

    public function delete(User $user, Series $series): bool
    {
        return $user->canManageClassroom($series->classroom_id);
    }

    public function viewReport(User $user, Series $series): bool
    {
        return $user->canManageClassroom($series->classroom_id);
    }
}
