<?php

namespace App\Actions\Classrooms;

use App\Actions\Access\RevokeStudentAccess;
use App\Enums\ClassroomRole;
use App\Models\AuditLog;
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
    public function __construct(private readonly RevokeStudentAccess $revokeAccess) {}

    public function handle(Classroom $classroom, string $email, ClassroomRole $role, User $actor): User
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

        // Pessoas não atravessam igrejas. Aluno: só quem já está numa turma que
        // você acompanha (os outros entram pelo link de acesso). Professor: só
        // uma conta sem vínculo com outra igreja. A mesma mensagem para "não
        // existe" e "é de outro lugar": não revela quem tem conta.
        $allowed = $user !== null && $user->isFreeFor($classroom->church_id) && ($role === ClassroomRole::Teacher
            || array_intersect($user->memberClassroomIds(), $actor->manageableClassroomIds()) !== []);

        if (! $allowed) {
            // Tentar trazer uma conta que existe mas não está ao seu alcance
            // (aluno de uma turma que você não acompanha, ou de outra igreja)
            // fica registrado, sem revelar nada a quem tentou.
            if ($user !== null) {
                AuditLog::query()->create([
                    'user_id' => $actor->id,
                    'source' => 'web',
                    'tool' => 'classroom.add_member',
                    'arguments' => ['role' => $role->value],
                    'subject_type' => $user->getMorphClass(),
                    'subject_id' => $user->id,
                    'classroom_id' => $classroom->id,
                    'status' => AuditLog::STATUS_DENIED,
                    'error' => 'Conta fora do alcance de quem adicionou.',
                ]);
            }

            throw ValidationException::withMessages([
                'email' => $role === ClassroomRole::Teacher
                    ? 'Não encontramos uma conta disponível com este e-mail.'
                    : 'Não encontramos essa pessoa nas suas turmas. Envie um convite pelo link de acesso.',
            ]);
        }

        $current = $user->roleIn($classroom);

        // Professor com senha não vira aluno por aqui: aluno recebe link pessoal,
        // que apaga a senha (seria um jeito de tomar o login de alguém).
        if ($current === ClassroomRole::Teacher && $role === ClassroomRole::Student && ! $user->isManaged()) {
            throw ValidationException::withMessages(['email' => "{$user->name} entra com e-mail e senha e não pode virar aluno(a) por aqui. Remova o vínculo de professor(a) primeiro."]);
        }

        if ($current !== null && $current !== $role && ! $actor->can('assignTeachers', $classroom)) {
            throw ValidationException::withMessages([
                'email' => "{$user->name} já faz parte da classe como ".mb_strtolower($current->label()).'.',
            ]);
        }

        $classroom->members()->syncWithoutDetaching([
            $user->id => ['role' => $role->value],
        ]);

        // Professor entra com e-mail e senha: links pessoais deixam de valer, e
        // também as sessões abertas por eles (o link entra com "lembrar de mim").
        if ($role === ClassroomRole::Teacher) {
            $this->revokeAccess->revokeForManager($user, $actor);
        }

        return $user;
    }
}
