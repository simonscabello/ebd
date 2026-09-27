<?php

namespace Tests\Feature\Auth;

use App\Enums\Gender;
use App\Models\AccessLink;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * "Completar cadastro": obrigatório para alunos antes de usar o app.
 */
class CompleteProfileTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::factory()->create(['slug' => 'jovens']);
    }

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Ana Paula Ferreira',
            'email' => 'Ana.Paula@Example.com ',
            'phone' => '(27) 98807-6040',
            'birth_date' => '2004-05-17',
            'gender' => 'female',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
            ...$overrides,
        ];
    }

    public function test_link_student_is_sent_to_complete_the_profile_from_any_page(): void
    {
        $student = User::factory()->managed()->studentOf($this->classroom)->create();

        foreach (['/', '/minha-semana', '/meu-progresso', '/conta', '/biblioteca'] as $url) {
            $this->actingAs($student)->get($url)->assertRedirect(route('onboarding.show'));
        }

        $this->actingAs($student)->get('/completar-cadastro')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/complete-profile')
                ->where('needsPassword', true)
                ->where('profile.name', $student->name)
                ->has('genders', 2));
    }

    public function test_logout_and_push_stay_available_while_the_profile_is_incomplete(): void
    {
        $student = User::factory()->managed()->studentOf($this->classroom)->create();

        $this->actingAs($student)
            ->postJson('/notificacoes/inscricao', [])
            ->assertStatus(422);

        $this->actingAs($student)->post('/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_json_requests_get_a_conflict_instead_of_a_redirect(): void
    {
        $student = User::factory()->managed()->studentOf($this->classroom)->create();

        $this->actingAs($student)->getJson('/minha-semana')->assertStatus(409);
    }

    public function test_teachers_admins_and_complete_students_are_not_bothered(): void
    {
        $teacher = User::factory()->teacherOf($this->classroom)->create(['birth_date' => null, 'gender' => null, 'phone' => null]);
        $admin = User::factory()->admin()->create(['birth_date' => null]);
        $student = User::factory()->studentOf($this->classroom)->create();
        $visitor = User::factory()->create(['birth_date' => null, 'gender' => null]);

        $this->actingAs($teacher)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($student)->get('/minha-semana')->assertOk();
        $this->actingAs($visitor)->get('/')->assertOk();

        $this->actingAs($student)->get('/completar-cadastro')->assertRedirect(route('my-week'));
    }

    public function test_student_with_password_but_missing_data_fills_only_the_data(): void
    {
        $student = User::factory()->studentOf($this->classroom)->create(['birth_date' => null, 'gender' => null]);

        $this->actingAs($student)->get('/minha-semana')->assertRedirect(route('onboarding.show'));
        $this->actingAs($student)->get('/completar-cadastro')
            ->assertInertia(fn (Assert $page) => $page->where('needsPassword', false));

        $this->actingAs($student)
            ->post('/completar-cadastro', $this->payload(['email' => $student->email, 'password' => null, 'password_confirmation' => null]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('my-week'));

        $student->refresh();
        $this->assertTrue(Hash::check('password', $student->password));
        $this->assertSame(Gender::Female, $student->gender);
        $this->assertTrue($student->hasCompleteProfile());
    }

    public function test_link_student_completes_the_profile_and_the_link_stops_working(): void
    {
        $teacher = User::factory()->teacherOf($this->classroom)->create();
        $student = User::factory()->managed()->studentOf($this->classroom)->create(['phone' => null]);

        $this->actingAs($teacher)->post("/admin/classes/jovens/membros/{$student->id}/link");
        $this->assertSame(1, AccessLink::query()->active()->count());

        $response = $this->actingAs($student)->post('/completar-cadastro', $this->payload());

        $response->assertSessionHasNoErrors()->assertRedirect(route('my-week'));
        $this->assertNotEmpty(collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_')));

        $student->refresh();
        $this->assertSame('Ana Paula Ferreira', $student->name);
        $this->assertSame('ana.paula@example.com', $student->email);
        $this->assertSame('5527988076040', $student->phone);
        $this->assertSame('2004-05-17', $student->birth_date->toDateString());
        $this->assertSame(Gender::Female, $student->gender);
        $this->assertTrue(Hash::check('senha-forte-123', $student->password));
        $this->assertSame(0, AccessLink::query()->active()->count());
        $this->assertAuthenticatedAs($student);

        $this->get('/minha-semana')->assertOk();
    }

    public function test_after_completing_the_student_returns_to_the_page_they_wanted(): void
    {
        $student = User::factory()->managed()->studentOf($this->classroom)->create();

        $this->actingAs($student)->get('/meu-progresso')->assertRedirect(route('onboarding.show'));
        $this->post('/completar-cadastro', $this->payload())->assertRedirect('/meu-progresso');
    }

    public function test_validation_requires_every_field_and_a_unique_email(): void
    {
        User::factory()->create(['email' => 'ocupado@example.com']);
        $student = User::factory()->managed()->studentOf($this->classroom)->create();

        $this->actingAs($student)
            ->post('/completar-cadastro', ['name' => ''])
            ->assertSessionHasErrors(['name', 'email', 'phone', 'birth_date', 'gender', 'password']);

        $this->actingAs($student)
            ->post('/completar-cadastro', $this->payload(['email' => 'OCUPADO@example.com']))
            ->assertSessionHasErrors(['email' => 'Este e-mail já é usado por outra conta. Use outro ou fale com o seu professor.']);

        $this->actingAs($student)
            ->post('/completar-cadastro', $this->payload(['birth_date' => now()->addDay()->toDateString(), 'gender' => 'x', 'phone' => 'abc']))
            ->assertSessionHasErrors(['birth_date', 'gender', 'phone']);

        $this->assertTrue($student->refresh()->isManaged());
    }

    public function test_the_requirement_can_be_switched_off(): void
    {
        config(['ebd.onboarding.required' => false]);
        $student = User::factory()->managed()->studentOf($this->classroom)->create();

        $this->actingAs($student)->get('/minha-semana')->assertOk();
    }
}
