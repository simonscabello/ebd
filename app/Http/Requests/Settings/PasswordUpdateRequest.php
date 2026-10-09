<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Aluno que entra pelo link pessoal cria a primeira senha sem "senha atual".
        return [
            'current_password' => $this->user()->isManaged() ? ['nullable'] : $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * O login por senha é por e-mail: sem e-mail, a senha não teria uso.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function ($validator) {
                if (blank($this->user()->email)) {
                    $validator->errors()->add('password', 'Cadastre um e-mail no seu perfil antes de criar uma senha.');
                }
            },
        ];
    }
}
