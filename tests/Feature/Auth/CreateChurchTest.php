<?php

namespace Tests\Feature\Auth;

use App\Models\Church;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_the_new_church_sees_nothing_of_the_existing_one(): void
    {
        $existing = Classroom::factory()->create(['slug' => 'jovens']);
        $this->post(route('register.store'), $this->payload());
        $admin = User::query()->where('email', 'marta@example.com')->sole();

        $this->actingAs($admin)->get('/admin/classes/jovens')->assertForbidden();
        $this->actingAs($admin)->post('/admin/classes', ['name' => 'Jovens', 'is_active' => true])->assertRedirect();

        $this->assertNotSame($existing->church_id, Classroom::query()->where('id', '!=', $existing->id)->value('church_id'));
    }
}
