<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Comportamentos necessários atrás do proxy do Railway e no bootstrap de produção.
 */
class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_healthcheck_is_ok_when_database_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_healthcheck_fails_without_exposing_details_when_database_is_down(): void
    {
        config(['app.debug' => false]);
        DB::shouldReceive('connection')->andThrow(new \RuntimeException('conexão recusada: senha=segredo'));

        $response = $this->get('/up');

        $response->assertStatus(500);
        $this->assertStringNotContainsString('segredo', (string) $response->getContent());
    }

    public function test_forwarded_https_from_the_proxy_is_trusted(): void
    {
        $response = $this->get('/login', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'ebd.up.railway.app',
            'X-Forwarded-Port' => '443',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('https://ebd.up.railway.app/', (string) $response->getContent());
    }

    public function test_promote_admins_only_promotes_existing_accounts(): void
    {
        $user = User::factory()->create(['email' => 'simon@example.com']);

        $this->artisan('ebd:promote-admins', ['emails' => ['SIMON@example.com', 'ninguem@example.com']])
            ->expectsOutputToContain('simon@example.com agora é administrador(a).')
            ->expectsOutputToContain('Conta não encontrada para ninguem@example.com')
            ->assertSuccessful();

        $this->assertTrue($user->refresh()->isAdmin());
        $this->assertDatabaseMissing('users', ['email' => 'ninguem@example.com']);
    }

    public function test_promote_admins_reads_configuration_and_is_idempotent(): void
    {
        $user = User::factory()->admin()->create(['email' => 'coordenacao@example.com']);
        config(['ebd.admin_emails' => ['coordenacao@example.com']]);

        $this->artisan('ebd:promote-admins')
            ->expectsOutputToContain('já é administrador(a)')
            ->assertSuccessful();

        $this->assertTrue($user->refresh()->isAdmin());
    }

    public function test_promote_admins_without_configuration_is_a_no_op(): void
    {
        config(['ebd.admin_emails' => []]);

        $this->artisan('ebd:promote-admins')
            ->expectsOutputToContain('Nenhum e-mail de administrador configurado.')
            ->assertSuccessful();
    }

    public function test_predeploy_runs_migrations_and_promotes_admins(): void
    {
        $user = User::factory()->create(['email' => 'simon@example.com']);
        config(['ebd.admin_emails' => ['simon@example.com']]);

        $this->artisan('ebd:predeploy')->assertSuccessful();

        $this->assertTrue($user->refresh()->isAdmin());
    }
}
