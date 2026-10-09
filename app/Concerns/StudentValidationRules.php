<?php

namespace App\Concerns;

use App\Enums\Gender;
use Illuminate\Validation\Rule;

/**
 * Dados do aluno: nome e telefone (cadastro pelo professor, tela de Membros e
 * servidor MCP) e o cadastro completo feito pelo próprio aluno.
 */
trait StudentValidationRules
{
    /**
     * @return array<string, mixed>
     */
    protected function studentRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => $this->phoneRules(),
        ];
    }

    /**
     * WhatsApp com DDD (e DDI, se for de fora do Brasil).
     *
     * @return list<string>
     */
    protected function phoneRules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:30', 'regex:/^[0-9 ()+.-]{8,30}$/'];
    }

    /**
     * @return list<string>
     */
    protected function birthDateRules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'date', 'before:today', 'after:1900-01-01'];
    }

    /**
     * @return list<mixed>
     */
    protected function genderRules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', Rule::enum(Gender::class)];
    }

    /**
     * @return array<string, string>
     */
    protected function studentMessages(): array
    {
        return [
            'phone.regex' => 'Use só números, com DDD (e DDI, se for de fora do Brasil).',
            'birth_date.before' => 'Informe uma data de nascimento válida.',
            'birth_date.after' => 'Informe uma data de nascimento válida.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function studentAttributes(): array
    {
        return [
            'name' => 'nome',
            'phone' => 'telefone',
            'birth_date' => 'data de nascimento',
            'gender' => 'gênero',
            'email' => 'e-mail',
        ];
    }
}
