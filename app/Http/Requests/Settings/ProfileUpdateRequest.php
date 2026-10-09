<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Concerns\StudentValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules, StudentValidationRules;

    /**
     * O login e a busca por e-mail usam minúsculas.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $rules = $this->profileRules($user->id);

        // Aluno que entra pelo link pessoal pode não ter e-mail.
        if ($user->isManaged()) {
            $rules['email'] = ['nullable', ...array_filter($rules['email'], fn ($rule) => $rule !== 'required')];
        }

        // Para alunos, WhatsApp, nascimento e gênero fazem parte do cadastro.
        $student = $user->isStudentAnywhere() && ! $user->canAccessAdmin();

        return [
            ...$rules,
            'phone' => $this->phoneRules(required: $student),
            'birth_date' => $this->birthDateRules(required: $student),
            'gender' => $this->genderRules(required: $student),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->studentMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->studentAttributes();
    }
}
