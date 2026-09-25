<?php

namespace App\Concerns;

/**
 * Nome e telefone de alunos cadastrados pelo professor (contas gerenciadas),
 * compartilhados pela tela de Membros e pelo servidor MCP.
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
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 ()+.-]{8,30}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function studentMessages(): array
    {
        return ['phone.regex' => 'Use só números, com DDD (e DDI, se for de fora do Brasil).'];
    }

    /**
     * @return array<string, string>
     */
    protected function studentAttributes(): array
    {
        return ['name' => 'nome', 'phone' => 'telefone'];
    }
}
