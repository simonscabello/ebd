<?php

namespace App\Actions\Classrooms;

use App\Actions\Access\IssueAccessLink;
use App\Enums\ClassroomRole;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cadastra um aluno só com nome (e telefone opcional), sem e-mail nem senha,
 * e já gera o link pessoal de acesso.
 */
class CreateManagedStudent
{
    public function __construct(
        private readonly IssueAccessLink $issueLink,
    ) {}

    /**
     * @return array{user: User, url: string}
     */
    public function handle(Classroom $classroom, string $name, ?string $phone, User $issuer): array
    {
        return DB::transaction(function () use ($classroom, $name, $phone, $issuer) {
            $user = new User;
            $user->name = trim($name);
            $user->phone = self::normalizePhone($phone);
            $user->save();

            $classroom->members()->attach($user->id, ['role' => ClassroomRole::Student->value]);
            $user->flushClassroomRoles();

            return ['user' => $user, 'url' => $this->issueLink->handle($user, $classroom, $issuer)];
        });
    }

    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return $digits !== '' && $digits !== null ? $digits : null;
    }
}
