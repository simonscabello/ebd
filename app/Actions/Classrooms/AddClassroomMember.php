<?php

namespace App\Actions\Classrooms;

use App\Enums\ClassroomRole;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Vincula uma pessoa já cadastrada a uma classe (ou atualiza seu papel).
 * Trocar o papel de quem já é da classe (ex.: professor virar aluno) é só
 * para quem pode designar professores (a administração).
 */
class AddClassroomMember
{
    public function handle(Classroom $classroom, string $email, ClassroomRole $role, User $actor): User
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'Nenhuma conta encontrada com este e-mail. Peça para a pessoa criar a conta primeiro.',
            ]);
        }

        $current = $user->roleIn($classroom);

        if ($current !== null && $current !== $role && ! $actor->can('assignTeachers', $classroom)) {
            throw ValidationException::withMessages([
                'email' => "{$user->name} já faz parte da classe como ".mb_strtolower($current->label()).'.',
            ]);
        }

        $classroom->members()->syncWithoutDetaching([
            $user->id => ['role' => $role->value],
        ]);

        // Professor entra com e-mail e senha: links pessoais deixam de valer.
        if ($role === ClassroomRole::Teacher) {
            $user->accessLinks()->active()->update(['revoked_at' => now()]);
        }

        return $user;
    }
}
