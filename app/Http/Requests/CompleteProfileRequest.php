<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\StudentValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Completar cadastro" do aluno. A senha só é pedida de quem ainda não tem.
 */
class CompleteProfileRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules, StudentValidationRules;

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($this->user()->id),
            'phone' => $this->phoneRules(required: true),
            'birth_date' => $this->birthDateRules(required: true),
            'gender' => $this->genderRules(required: true),
            ...($this->user()->isManaged() ? ['password' => $this->passwordRules()] : []),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->studentMessages(),
            'email.unique' => 'Este e-mail já é usado por outra conta. Use outro ou fale com o seu professor.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->studentAttributes();
    }

    /**
     * Dados já validados, no formato que CompleteStudentProfile recebe.
     *
     * @return array{name: string, email: string, phone: string, birth_date: string, gender: string, password?: string}
     */
    public function profile(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'email' => $this->string('email')->toString(),
            'phone' => $this->string('phone')->toString(),
            'birth_date' => $this->string('birth_date')->toString(),
            'gender' => $this->string('gender')->toString(),
            ...($this->user()->isManaged() ? ['password' => $this->string('password')->toString()] : []),
        ];
    }
}
