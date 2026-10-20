<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\CreateChurchController;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Support\SessionKey;
use Tests\TestCase;

/**
 * "Criar minha igreja": o cadastro público cria a pessoa, a igreja e o
 * primeiro programa, e a pessoa vira administradora daquela igreja (só dela).
 */
class CreateChurchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    }

    private function payload(array $data = []): array
    {
        return [
            'name' => 'Marta Lima',
            'email' => 'marta@example.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
            'church_name' => 'Igreja Batista Esperança',
            ...$data,
        ];
    }

    public function test_signing_up_creates_the_church_with_its_admin_and_program(): void
    {
        $this->post(route('register.store'), $this->payload())->assertRedirect(route('home', absolute: false));

        $user = User::query()->where('email', 'marta@example.com')->sole();
        $church = Church::query()->where('name', 'Igreja Batista Esperança')->sole();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->isChurchAdminOf($church));
        $this->assertFalse($user->isAdmin());
        $this->assertSame(['Escola Bíblica Dominical', 'EBD'], [$church->defaultProgram()->name, $church->defaultProgram()->short_name]);
        $this->assertSame('igreja-batista-esperanca', $church->slug);

        // A pessoa nova cai na gestão, no passo a passo.
        $this->actingAs($user)->get('/')->assertRedirect('/admin');
    }

    public function test_a_custom_first_program(): void
    {
        $this->post(route('register.store'), $this->payload(['program_name' => 'Discipulado']));

        $program = Church::query()->where('name', 'Igreja Batista Esperança')->sole()->defaultProgram();
        $this->assertSame(['Discipulado', 'Discipulado'], [$program->name, $program->short_name]);
    }

    public function test_validation(): void
    {
        User::factory()->create(['email' => 'marta@example.com']);

        $this->post(route('register.store'), $this->payload())->assertSessionHasErrors('email');
        $this->post(route('register.store'), $this->payload(['email' => 'outra@example.com', 'church_name' => '']))->assertSessionHasErrors('church_name');

        $this->assertSame(0, Church::query()->where('name', 'Igreja Batista Esperança')->count());
        $this->assertGuest();
    }

    public function test_a_logged_in_person_creates_another_church_with_the_same_login(): void
    {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->studentOf($classroom)->create();

        $this->actingAs($student)->post('/criar-igreja', ['church_name' => 'Grupo da Quarta', 'program_name' => 'Pequeno grupo'])
            ->assertRedirect('/admin');

        $church = Church::query()->where('name', 'Grupo da Quarta')->sole();
        $this->assertTrue($student->fresh()->isChurchAdminOf($church));
        $this->assertTrue($student->fresh()->isMemberOf($classroom));
    }

    public function test_an_account_without_password_does_not_create_a_church(): void
    {
        // Viraria administradora, o link passaria a ser recusado e ela ficaria
        // trancada. Sem a tela "Completar cadastro" no caminho, a recusa é daqui.
        config(['ebd.onboarding.required' => false]);
        $classroom = Classroom::factory()->create();
        $student = User::factory()->managed()->studentOf($classroom)->create();
        $churches = Church::query()->count();

        $this->actingAs($student)->get('/criar-igreja')->assertRedirect('/');
        $this->actingAs($student)->post('/criar-igreja', ['church_name' => 'Grupo da Quarta'])
            ->assertSessionHasErrors(['church_name' => CreateChurchController::NEEDS_PASSWORD]);

        $this->assertSame($churches, Church::query()->count());
        $this->assertSame([], $student->fresh()->administeredChurchIds());
    }

    public function test_the_new_church_sees_nothing_of_the_existing_one(): void
    {
        $existing = Classroom::factory()->create(['slug' => 'jovens']);
        $this->post(route('register.store'), $this->payload());
        $admin = User::query()->where('email', 'marta@example.com')->sole();

        $this->actingAs($admin)->get('/admin/classes/jovens')->assertForbidden();
        $this->actingAs($admin)->post('/admin/classes', ['name' => 'Jovens', 'is_active' => true])->assertRedirect();

        $this->assertNotSame($existing->church_id, Classroom::query()->where('id', '!=', $existing->id)->value('church_id'));
    }

    public function test_signup_is_limited_per_ip_per_minute(): void
    {
        // Envios com erro contam também: o limite é por tentativa.
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('register.store'), $this->payload(['church_name' => '']))->assertSessionHasErrors('church_name');
        }

        $this->post(route('register.store'), $this->payload())->assertTooManyRequests();
        $this->assertSame(0, Church::query()->where('name', 'Igreja Batista Esperança')->count());

        // Outro IP não é afetado.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->post(route('register.store'), $this->payload())
            ->assertRedirect(route('home', absolute: false));
    }

    public function test_a_forged_forwarded_for_does_not_escape_the_limit(): void
    {
        // No Railway, o IP de verdade vem em X-Real-IP; o X-Forwarded-For o
        // visitante pode mandar preenchido com o que quiser.
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders(['X-Real-IP' => '203.0.113.7', 'X-Forwarded-For' => "198.51.100.{$i}"])
                ->post(route('register.store'), $this->payload(['church_name' => '']));
        }

        $this->withHeaders(['X-Real-IP' => '203.0.113.7', 'X-Forwarded-For' => '198.51.100.99'])
            ->post(route('register.store'), $this->payload())
            ->assertTooManyRequests();

        // Outro visitante (outro X-Real-IP) segue normal.
        $this->withHeaders(['X-Real-IP' => '203.0.113.8'])
            ->post(route('register.store'), $this->payload())
            ->assertRedirect(route('home', absolute: false));
    }

    public function test_signup_is_limited_per_ip_per_day(): void
    {
        for ($i = 0; $i < 20; $i++) {
            if ($i > 0 && $i % 5 === 0) {
                $this->travel(61)->seconds();
            }

            $this->post(route('register.store'), $this->payload(['church_name' => '']))->assertSessionHasErrors('church_name');
        }

        $this->travel(61)->seconds();
        $this->post(route('register.store'), $this->payload())->assertTooManyRequests();

        $this->travel(1)->days();
        $this->post(route('register.store'), $this->payload())->assertRedirect(route('home', absolute: false));
    }

    public function test_the_form_gets_a_friendly_message_when_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('register.store'), $this->payload(['church_name' => '']));
        }

        $this->from(route('register'))
            ->post(route('register.store'), $this->payload(), ['X-Inertia' => 'true'])
            ->assertRedirect(route('register'))
            ->assertSessionHas(SessionKey::FLASH_DATA, fn (array $flash) => $flash['toast']['message'] === 'Muitas tentativas seguidas. Espere um minuto e tente de novo.');
    }

    public function test_a_limited_page_visit_does_not_redirect_back(): void
    {
        // Num GET, voltar à página anterior poderia cair no mesmo limite, em laço.
        Route::middleware(['web', 'throttle:1,1'])->get('/_teste/limite', fn () => 'ok');

        $this->get('/_teste/limite')->assertOk();
        $this->from('/biblioteca')->get('/_teste/limite', ['X-Inertia' => 'true'])->assertTooManyRequests();
    }

    public function test_creating_another_church_is_limited_per_ip(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->post('/criar-igreja', ['church_name' => ''])->assertSessionHasErrors('church_name');
        }

        $this->actingAs($user)->post('/criar-igreja', ['church_name' => 'Grupo da Quarta'])->assertTooManyRequests();
        $this->assertSame(0, Church::query()->where('name', 'Grupo da Quarta')->count());
    }

    public function test_one_account_administers_at_most_three_churches(): void
    {
        $user = User::factory()->create();
        $user->administeredChurches()->attach(Church::factory()->count(2)->create());
        // A biblioteca oficial não entra na conta.
        $user->administeredChurches()->attach(Church::factory()->create(['is_official' => true]));

        $this->actingAs($user)->post('/criar-igreja', ['church_name' => 'Terceira'])->assertRedirect('/admin');

        $this->actingAs($user)->post('/criar-igreja', ['church_name' => 'Quarta'])
            ->assertSessionHasErrors(['church_name' => 'Cada conta pode administrar até 3 igrejas ou grupos. Para criar outra, peça a alguém da liderança que crie com a conta dele.']);
        $this->assertSame(0, Church::query()->where('name', 'Quarta')->count());

        // O dono do sistema não tem teto.
        $owner = User::factory()->admin()->create();
        $owner->administeredChurches()->attach(Church::factory()->count(3)->create());
        $this->actingAs($owner)->post('/criar-igreja', ['church_name' => 'Quarta'])->assertRedirect('/admin');
    }

    public function test_a_slug_taken_at_the_same_time_gets_the_next_suffix(): void
    {
        $this->raceForSlug(times: 1);

        $this->post(route('register.store'), $this->payload())->assertRedirect(route('home', absolute: false));

        $this->assertSame('igreja-batista-esperanca-2', Church::query()->where('name', 'Igreja Batista Esperança')->sole()->slug);
        $this->assertAuthenticated();
    }

    public function test_a_slug_that_keeps_colliding_falls_back_to_a_random_suffix(): void
    {
        $this->raceForSlug(times: 2);

        $this->post(route('register.store'), $this->payload())->assertRedirect(route('home', absolute: false));

        $this->assertMatchesRegularExpression('/^igreja-batista-esperanca-[a-z0-9]{6}$/', Church::query()->where('name', 'Igreja Batista Esperança')->sole()->slug);
    }

    /**
     * Simula outro cadastro com o mesmo nome: entre a checagem e o INSERT, uma
     * segunda conexão grava e confirma o slug escolhido, como faria uma
     * requisição paralela. As linhas dela são apagadas no fim do teste.
     */
    private function raceForSlug(int $times): void
    {
        config(['database.connections.race' => config('database.connections.'.config('database.default'))]);
        $race = DB::connection('race');

        Church::creating(function (Church $church) use (&$times, $race) {
            if ($times-- > 0) {
                $race->table('churches')->insert([
                    'name' => 'Concorrente',
                    'slug' => $church->slug,
                    'timezone' => 'America/Sao_Paulo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->beforeApplicationDestroyed(function () use ($race) {
            $race->table('churches')->where('name', 'Concorrente')->delete();
            $race->disconnect();
        });
    }
}
