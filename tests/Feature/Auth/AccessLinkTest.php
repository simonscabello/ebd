<?php

namespace Tests\Feature\Auth;

use App\Actions\Access\LoginWithAccessLink;
use App\Models\AccessLink;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Link pessoal de acesso dos alunos (enviado pelo WhatsApp).
 */
class AccessLinkTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::factory()->create(['slug' => 'adultos']);
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
    }

    /**
     * Gera o link pelo painel e devolve o token (lido do flash, como a tela faz).
     */
    private function issue(User $student, ?User $as = null): string
    {
        $this->actingAs($as ?? $this->teacher)
            ->post("/admin/classes/adultos/membros/{$student->id}/link")
            ->assertSessionHasNoErrors();

        $url = $this->flashedLinkUrl();

        Auth::logout();

        return (string) str($url)->after('#');
    }

    /**
     * O link só existe no flash da resposta (Inertia::flash), nunca no banco.
     */
    private function flashedLinkUrl(): ?string
    {
        $url = session('inertia.flash_data.accessLink.url');

        return is_string($url) ? $url : null;
    }

    public function test_teacher_creates_student_by_name_and_the_link_logs_in(): void
    {
        $this->actingAs($this->teacher)
            ->post('/admin/classes/adultos/alunos', ['name' => 'Maria Santos', 'phone' => '+55 (11) 99999-8888'])
            ->assertSessionHasNoErrors();

        $student = User::query()->where('name', 'Maria Santos')->sole();
        $this->assertNull($student->email);
        $this->assertTrue($student->isManaged());
        $this->assertSame('5511999998888', $student->phone);
        $this->assertTrue($student->isMemberOf($this->classroom));

        $url = $this->flashedLinkUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString('/entrar#', $url);
        $token = (string) str($url)->after('#');
        $this->assertSame(48, strlen($token));

        // Só o hash fica no banco.
        $this->assertDatabaseMissing('access_links', ['token_hash' => $token]);
        $this->assertDatabaseHas('access_links', ['user_id' => $student->id, 'token_hash' => hash('sha256', $token)]);

        Auth::logout();
        $response = $this->post('/entrar', ['token' => $token]);

        // Aluno novo completa o cadastro (e-mail, senha...) no primeiro acesso.
        $response->assertRedirect(route('onboarding.show'));
        $this->assertAuthenticatedAs($student);
        $this->assertNotEmpty(collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_')));
        $this->assertSame(1, AccessLink::query()->sole()->use_count);
    }

    /**
     * A sessão dura 2 horas; depois disso é o cookie "lembrar de mim" que
     * mantém o aluno do link logado. Sem senha, o Laravel recusava o cookie.
     */
    public function test_remember_cookie_keeps_link_student_logged_in_after_the_session_expires(): void
    {
        $student = User::factory()->managed()->studentOf($this->classroom)->create();
        $token = $this->issue($student);

        $response = $this->post('/entrar', ['token' => $token]);
        $remember = collect($response->headers->getCookies())
            ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));
        $this->assertNotNull($remember);

        // Sessão expirada: nada na sessão, só o cookie no aparelho.
        Auth::forgetGuards();
        $this->flushSession();

        $this->withUnencryptedCookie($remember->getName(), (string) $remember->getValue())
            ->get('/completar-cadastro')
            ->assertOk();

        $this->assertAuthenticatedAs($student);
    }

    public function test_get_never_logs_in_so_link_previews_are_harmless(): void
    {
        $student = User::factory()->managed()->studentOf($this->classroom)->create();
        $this->issue($student);

        $this->get('/entrar')->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/access-link'));

        $this->assertGuest();
        $this->assertSame(0, AccessLink::query()->sole()->use_count);
    }

    public function test_invalid_revoked_and_expired_links_give_the_same_generic_error(): void
    {
        $student = User::factory()->managed()->studentOf($this->classroom)->create();
        $token = $this->issue($student);

        $this->post('/entrar', ['token' => str_repeat('a', 48)])->assertSessionHasErrors('token');
        $this->post('/entrar', ['token' => 'curto'])->assertSessionHasErrors('token');

        AccessLink::query()->update(['expires_at' => now()->subDay()]);
        $this->post('/entrar', ['token' => $token])->assertSessionHasErrors(['token' => LoginWithAccessLink::INVALID]);

        AccessLink::query()->update(['expires_at' => null, 'revoked_at' => now()]);
        $this->post('/entrar', ['token' => $token])->assertSessionHasErrors('token');

        $this->assertGuest();
    }

    public function test_generating_a_new_link_invalidates_the_previous_one(): void
    {
        $student = User::factory()->managed()->studentOf($this->classroom)->create();
        $old = $this->issue($student);
        $new = $this->issue($student);

        $this->assertNotSame($old, $new);
        $this->assertSame(1, AccessLink::query()->active()->count());

        $this->post('/entrar', ['token' => $old])->assertSessionHasErrors('token');
        $this->post('/entrar', ['token' => $new])->assertRedirect(route('onboarding.show'));
    }

    public function test_blocking_access_revokes_link_and_forgets_devices(): void
    {
        config(['session.driver' => 'database']);
        $student = User::factory()->managed()->studentOf($this->classroom)->create();
        $token = $this->issue($student);
        $rememberToken = $student->refresh()->remember_token;
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $student->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->teacher)
            ->delete("/admin/classes/adultos/membros/{$student->id}/link")
            ->assertSessionHasNoErrors();

        $this->assertNotSame($rememberToken, $student->refresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['user_id' => $student->id]);
        Auth::logout();
        $this->post('/entrar', ['token' => $token])->assertSessionHasErrors('token');
    }

    public function test_links_are_never_issued_for_or_used_by_staff(): void
    {
        $otherTeacher = User::factory()->teacherOf($this->classroom)->create();

        $this->actingAs($this->teacher)
            ->post("/admin/classes/adultos/membros/{$otherTeacher->id}/link")
            ->assertSessionHasErrors('user');

        // Mesmo que um link exista (ex.: aluno promovido), ele não autentica staff.
        $student = User::factory()->managed()->studentOf($this->classroom)->create();
        $token = $this->issue($student);
        $student->forceFill(['is_admin' => true])->save();

        $this->post('/entrar', ['token' => $token])->assertSessionHasErrors('token');
        $this->assertGuest();
    }

    public function test_promotion_to_teacher_revokes_links(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->studentOf($this->classroom)->create();
        $this->issue($student, $admin);

        $this->actingAs($admin)->post('/admin/classes/adultos/membros', ['email' => $student->email, 'role' => 'teacher']);

        $this->assertSame(0, AccessLink::query()->active()->count());
    }

    /**
     * Sem e-mail de recuperação, quem esquece a senha pede um link novo ao
     * professor. Entrar por ele apaga a senha antiga e leva a criar outra.
     */
    public function test_teacher_issues_a_recovery_link_that_replaces_a_forgotten_password(): void
    {
        $student = User::factory()->studentOf($this->classroom)->create(['email' => 'rute@example.com']);
        $token = $this->issue($student);

        $this->post('/entrar', ['token' => $token])->assertRedirect(route('onboarding.show'));

        $this->assertAuthenticatedAs($student);
        $this->assertNull($student->refresh()->password);
        $this->get('/minha-semana')->assertRedirect(route('onboarding.show'));

        $this->post('/completar-cadastro', [
            'name' => $student->name,
            'email' => 'rute@example.com',
            'phone' => $student->phone,
            'birth_date' => $student->birth_date->toDateString(),
            'gender' => $student->gender->value,
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('senha-nova-123', $student->refresh()->password));
        $this->assertSame(0, AccessLink::query()->active()->count());
        $this->post('/entrar', ['token' => $token])->assertSessionHasErrors('token');
    }

    public function test_teacher_cannot_block_someone_who_teaches_another_classroom(): void
    {
        $colleague = User::factory()->teacherOf(Classroom::factory()->create())->studentOf($this->classroom)->create();

        $this->actingAs($this->teacher)
            ->delete("/admin/classes/adultos/membros/{$colleague->id}/link")
            ->assertForbidden();
    }

    public function test_creating_a_password_in_the_profile_revokes_the_personal_link(): void
    {
        $student = User::factory()->studentOf($this->classroom)->create();
        $this->issue($student);
        $this->assertSame(1, AccessLink::query()->active()->count());

        $this->actingAs($student)
            ->put('/conta/senha', ['current_password' => 'password', 'password' => 'nova-senha-123', 'password_confirmation' => 'nova-senha-123'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, AccessLink::query()->active()->count());
        $this->assertAuthenticatedAs($student);
    }

    public function test_teacher_of_another_classroom_cannot_manage_links(): void
    {
        $outsider = User::factory()->teacherOf(Classroom::factory()->create())->create();
        $student = User::factory()->managed()->studentOf($this->classroom)->create();

        $this->actingAs($outsider)->post("/admin/classes/adultos/membros/{$student->id}/link")->assertForbidden();
        $this->actingAs($outsider)->post('/admin/classes/adultos/alunos', ['name' => 'X'])->assertForbidden();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        foreach (range(1, 10) as $ignored) {
            $this->post('/entrar', ['token' => str_repeat('b', 48)]);
        }

        $this->post('/entrar', ['token' => str_repeat('b', 48)])->assertTooManyRequests();
    }

    public function test_link_replaces_another_logged_user_on_the_same_device(): void
    {
        $student = User::factory()->managed()->studentOf($this->classroom)->create();
        $token = $this->issue($student);
        $other = User::factory()->studentOf($this->classroom)->create();

        $this->actingAs($other)->post('/entrar', ['token' => $token])->assertRedirect(route('onboarding.show'));

        $this->assertAuthenticatedAs($student);
    }

    public function test_managed_student_completes_the_profile_before_using_the_settings(): void
    {
        $student = User::factory()->managed()->studentOf($this->classroom)->create(['name' => 'João']);

        $this->actingAs($student)->patch('/conta/perfil', ['name' => 'João Pereira'])->assertRedirect(route('onboarding.show'));
        $this->actingAs($student)->get('/conta/seguranca')->assertRedirect(route('onboarding.show'));

        $this->assertSame('João', $student->refresh()->name);
    }

    public function test_managed_account_without_password_cannot_log_in_by_email_form(): void
    {
        $student = User::factory()->managed()->create(['email' => 'aluno@example.com']);

        $this->post(route('login.store'), ['email' => 'aluno@example.com', 'password' => ''])->assertSessionHasErrors();
        $this->post(route('login.store'), ['email' => 'aluno@example.com', 'password' => 'qualquer'])->assertSessionHasErrors();
        $this->assertGuest();
        $this->assertNull($student->refresh()->password);
    }
}
