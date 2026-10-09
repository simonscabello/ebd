<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

/**
 * Regra de senha do app: 6 caracteres no mínimo; em produção, também letras,
 * números e fora das listas de senhas vazadas.
 */
class PasswordRulesTest extends TestCase
{
    private function passes(string $password): bool
    {
        return Validator::make(['password' => $password], ['password' => Password::default()])->passes();
    }

    public function test_six_characters_are_enough(): void
    {
        $this->assertFalse($this->passes('abc12'));
        $this->assertTrue($this->passes('abc123'));
    }

    public function test_production_also_requires_letters_and_numbers(): void
    {
        // Nenhuma senha "vazada" nesta checagem: a consulta externa é simulada.
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
        $this->app->detectEnvironment(fn () => 'production');

        $this->assertFalse($this->passes('abcdef'));
        $this->assertFalse($this->passes('123456'));
        $this->assertFalse($this->passes('ab12'));
        $this->assertTrue($this->passes('abc123'));
    }
}
