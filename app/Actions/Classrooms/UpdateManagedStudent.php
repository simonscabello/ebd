<?php

namespace App\Actions\Classrooms;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Corrige nome e telefone de um aluno.
 *
 * Mesma regra do link de acesso: professor só mexe em contas sem senha
 * (criadas por ele); conta com senha é da própria pessoa, que a edita no
 * perfil. A administração pode corrigir qualquer aluno.
 */
class UpdateManagedStudent
{
    /**
     * @param  array{name?: string, phone?: string|null}  $data
     */
    public function handle(User $student, array $data, User $editor): User
    {
        if ($student->isAdmin() || $student->canAccessAdmin()) {
            throw ValidationException::withMessages(['student' => 'Professores e administradores editam os próprios dados no perfil.']);
        }

        if (! $student->isManaged() && ! $editor->isAdmin()) {
            throw ValidationException::withMessages(['student' => 'Esta pessoa tem senha própria: ela mesma atualiza os dados no perfil.']);
        }

        if (array_key_exists('name', $data)) {
            $student->name = trim((string) $data['name']);
        }

        if (array_key_exists('phone', $data)) {
            $student->phone = CreateManagedStudent::normalizePhone($data['phone']);
        }

        $student->save();

        return $student;
    }
}
