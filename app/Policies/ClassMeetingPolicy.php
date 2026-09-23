<?php

namespace App\Policies;

use App\Models\ClassMeeting;
use App\Models\User;

/**
 * Agenda da classe: quem gerencia a classe (professores dela e administradores).
 */
class ClassMeetingPolicy
{
    public function update(User $user, ClassMeeting $meeting): bool
    {
        return $user->canManageClassroom($meeting->classroom_id);
    }

    public function delete(User $user, ClassMeeting $meeting): bool
    {
        return $user->canManageClassroom($meeting->classroom_id);
    }

    public function takeAttendance(User $user, ClassMeeting $meeting): bool
    {
        return $user->canManageClassroom($meeting->classroom_id);
    }
}
