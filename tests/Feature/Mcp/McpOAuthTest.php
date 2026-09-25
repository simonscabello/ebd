<?php

namespace Tests\Feature\Mcp;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Porta de entrada do servidor MCP: descoberta OAuth, registro de aplicativos
 * (só os do Claude e clientes locais), consentimento e a rota /mcp protegida.
 */
class McpOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Em produção as chaves vêm de PASSPORT_PRIVATE_KEY/PASSPORT_PUBLIC_KEY.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);

        config([
            'passport.private_key' => $private,
            'passport.public_key' => openssl_pkey_get_details($key)['key'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function initialize(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'teste', 'version' => '1.0'],
            ],
        ];
    }

    public function test_discovery_metadata_points_to_passport(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJsonPath('resource', url('/mcp'));

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
            ->assertJsonPath('registration_endpoint', url('/oauth/register'));
    }

    public function test_only_claude_and_local_clients_can_register(): void
    {
        $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])
            ->assertCreated()
            ->assertJsonPath('token_endpoint_auth_method', 'none');

        $this->postJson('/oauth/register', ['client_name' => 'Claude Code', 'redirect_uris' => ['http://localhost:53412/callback']])
            ->assertCreated();

        $this->postJson('/oauth/register', ['client_name' => 'Outro', 'redirect_uris' => ['https://evil.example.com/callback']])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');

        $this->assertSame(2, Client::query()->count());
    }

    public function test_consent_screen_is_in_portuguese_and_hides_approval_from_students(): void
    {
        $classroom = Classroom::factory()->create();
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Claude', ['https://claude.ai/api/mcp/auth_callback'], confidential: false);
        $query = http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'abc',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', str_repeat('a', 64), true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);

        $this->get("/oauth/authorize?{$query}")->assertRedirect(route('login'));

        $this->actingAs(User::factory()->teacherOf($classroom)->create())
            ->get("/oauth/authorize?{$query}")
            ->assertOk()
            ->assertSee('Conectar Claude')
            ->assertSee('Permitir acesso');

        $this->actingAs(User::factory()->studentOf($classroom)->create())
            ->get("/oauth/authorize?{$query}")
            ->assertOk()
            ->assertSee('só para professores e administradores')
            ->assertDontSee('Permitir acesso');
    }

    public function test_after_login_the_consent_screen_opens_as_a_full_page(): void
    {
        // O Fortify devolve para /oauth/authorize numa navegação do Inertia;
        // a tela em Blade não pode abrir dentro dela.
        $this->actingAs(User::factory()->create())
            ->get('/oauth/authorize?client_id=x', ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', url('/oauth/authorize?client_id=x'));
    }

    public function test_mcp_endpoint_requires_a_teacher_token(): void
    {
        $classroom = Classroom::factory()->create();

        $this->postJson('/mcp', $this->initialize())
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');

        Passport::actingAs(User::factory()->studentOf($classroom)->create(), ['mcp:use']);
        $this->postJson('/mcp', $this->initialize())->assertForbidden();

        Passport::actingAs(User::factory()->teacherOf($classroom)->create(), ['mcp:use']);
        $this->postJson('/mcp', $this->initialize())
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'EBD');
    }
}
