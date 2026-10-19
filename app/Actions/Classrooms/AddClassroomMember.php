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

        // Pessoas não atravessam igrejas. Aluno: só quem já está numa turma que
        // você acompanha (os outros entram pelo link de acesso). Professor: só
        // uma conta sem vínculo com outra igreja. A mesma mensagem para "não
        // existe" e "é de outro lugar": não revela quem tem conta.
        $allowed = $user !== null && ($role === ClassroomRole::Teacher
            ? array_diff($user->memberChurchIds(), [$classroom->church_id]) === []
                && array_diff($user->administeredChurchIds(), [$classroom->church_id]) === []
            : array_intersect($user->memberClassroomIds(), $actor->manageableClassroomIds() ?? []) !== []);

        if (! $allowed) {
            throw ValidationException::withMessages([
                'email' => $role === ClassroomRole::Teacher
                    ? 'Não encontramos uma conta disponível com este e-mail.'
                    : 'Não encontramos essa pessoa nas suas turmas. Envie um convite pelo link de acesso.',
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
