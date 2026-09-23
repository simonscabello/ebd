<?php

namespace App\Actions\Classrooms;

use App\Enums\ClassroomRole;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Vincula uma pessoa já cadastrada a uma classe (ou atualiza seu papel).
 */
class AddClassroomMember
{
    public function handle(Classroom $classroom, string $email, ClassroomRole $role): User
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'Nenhuma conta encontrada com este e-mail. Peça para a pessoa criar a conta primeiro.',
            ]);
        }

        $classroom->members()->syncWithoutDetaching([
            $user->id => ['role' => $role->value],
        ]);

        return $user;
    }
}
