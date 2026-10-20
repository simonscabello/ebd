<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

/**
 * Domínio próprio: o endereço antigo e o www levam para o domínio principal,
 * no mesmo caminho, para os links pessoais já enviados continuarem valendo.
 */
class CanonicalHostTest extends TestCase
{
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
        $this->post('https://ebd.up.railway.app/mcp')
            ->assertStatus(308)
            ->assertRedirect('https://usedidache.com.br/mcp');
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
