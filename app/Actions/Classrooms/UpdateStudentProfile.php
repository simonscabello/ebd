<?php

namespace App\Actions\Classrooms;

use App\Enums\Gender;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Dados do aluno corrigidos pelo professor: nome, WhatsApp, nascimento e
 * gênero, de qualquer aluno (com ou sem senha). O e-mail é o login da pessoa:
 * o professor só o altera em conta ainda sem senha; a administração, em
 * qualquer uma. Professores e administradores editam os próprios dados no perfil.
 */
class UpdateStudentProfile
{
    /**
     * @param  array{name?: string, phone?: string|null, birth_date?: string|null, gender?: string|null, email?: string|null}  $data
     */
    public function handle(User $student, array $data, User $editor): User
    {
        if ($student->isAdmin() || $student->canAccessAdmin()) {
            throw ValidationException::withMessages(['student' => 'Professores e administradores editam os próprios dados no perfil.']);
        }

        if (array_key_exists('email', $data) && ! $student->isManaged() && ! $editor->isAdmin()) {
            throw ValidationException::withMessages(['email' => 'O e-mail é o login desta pessoa: só ela, no perfil, ou a administração podem trocá-lo.']);
        }

        if (array_key_exists('name', $data)) {
            $student->name = trim((string) $data['name']);
        }

        if (array_key_exists('phone', $data)) {
            $student->phone = CreateManagedStudent::normalizePhone($data['phone']);
        }

        if (array_key_exists('birth_date', $data)) {
            $student->birth_date = $data['birth_date'] !== null ? Carbon::parse($data['birth_date']) : null;
        }

        if (array_key_exists('gender', $data)) {
            $student->gender = $data['gender'] !== null ? Gender::from($data['gender']) : null;
        }

        if (array_key_exists('email', $data)) {
            $student->email = filled($data['email']) ? mb_strtolower(trim((string) $data['email'])) : null;
        }

        $student->save();

        return $student;
    }
}
