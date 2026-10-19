<?php

namespace App\Actions\Fortify;

use App\Actions\Churches\CreateChurchWithAdmin;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Cadastro público = "Criar minha igreja": a pessoa, a igreja e o primeiro
 * programa. Alunos e professores entram por convite ou link pessoal.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private readonly CreateChurchWithAdmin $createChurch) {}

    /**
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'church_name' => ['required', 'string', 'max:120'],
            'program_name' => ['nullable', 'string', 'max:100'],
        ], [], ['church_name' => 'nome da igreja ou grupo', 'program_name' => 'nome do programa'])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $this->createChurch->handle($user, $input['church_name'], $input['program_name'] ?? null);

            return $user;
        });
    }
}
