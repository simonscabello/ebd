<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Domínio próprio: o endereço antigo e o www levam para o domínio principal,
 * no mesmo caminho, para os links pessoais já enviados continuarem valendo.
 */
class CanonicalHostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ebd.canonical_host' => 'usedidache.com.br',
            'ebd.redirect_hosts' => ['ebd.up.railway.app', 'www.usedidache.com.br'],
        ]);
    }

    public function test_old_hosts_redirect_permanently_keeping_path_and_query(): void
    {
        $this->get('https://ebd.up.railway.app/entrar/abc123?x=1')
            ->assertStatus(301)
            ->assertRedirect('https://usedidache.com.br/entrar/abc123?x=1');

        $this->get('https://www.usedidache.com.br/apoie')
            ->assertRedirect('https://usedidache.com.br/apoie');
    }

    public function test_non_get_requests_keep_their_method(): void
    {
        $this->post('https://ebd.up.railway.app/login')
            ->assertStatus(308)
            ->assertRedirect('https://usedidache.com.br/login');
    }

    public function test_mcp_oauth_and_push_stay_on_the_old_host(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        config([
            'passport.private_key' => $private,
            'passport.public_key' => openssl_pkey_get_details($key)['key'],
        ]);

        // Clientes MCP com o endereço antigo: o redirect tiraria o Authorization.
        $this->postJson('https://ebd.up.railway.app/mcp')->assertUnauthorized();
        $this->getJson('https://ebd.up.railway.app/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJsonPath('resource', 'https://ebd.up.railway.app/mcp');
        $this->postJson('https://ebd.up.railway.app/oauth/token')->assertStatus(400);

        // O service worker antigo renova a inscrição no próprio host.
        $this->get('https://ebd.up.railway.app/notificacoes/chave')->assertSuccessful();
        $this->postJson('https://ebd.up.railway.app/notificacoes/renovacao')->assertUnprocessable();

        // A tela de autorização é a pessoa no navegador: vai para o domínio novo.
        $this->get('https://ebd.up.railway.app/oauth/authorize?client_id=x')
            ->assertRedirect('https://usedidache.com.br/oauth/authorize?client_id=x');
    }

    public function test_the_canonical_host_may_be_configured_with_a_scheme(): void
    {
        config(['ebd.canonical_host' => 'https://UseDidache.com.br/']);

        $this->get('https://ebd.up.railway.app/apoie')
            ->assertRedirect('https://usedidache.com.br/apoie');
    }

    public function test_the_canonical_host_in_the_redirect_list_does_not_loop(): void
    {
        config(['ebd.redirect_hosts' => ['ebd.up.railway.app', 'usedidache.com.br']]);

        $this->get('https://usedidache.com.br/login')->assertOk();
    }

    public function test_the_canonical_host_and_the_health_check_are_untouched(): void
    {
        $this->get('https://usedidache.com.br/up')->assertOk();
        $this->get('https://ebd.up.railway.app/up')->assertOk();
        $this->get('http://10.0.0.5/up')->assertOk();
    }

    public function test_it_does_nothing_until_configured(): void
    {
        config(['ebd.canonical_host' => null]);

        $this->get('https://ebd.up.railway.app/up')->assertOk();
        $this->get('https://ebd.up.railway.app/login')->assertStatus(200);
    }
}
