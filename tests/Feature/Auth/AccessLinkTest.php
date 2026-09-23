<?php

namespace Tests\Feature\Auth;

use App\Models\AccessLink;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $response->assertRedirect(route('my-week'));
        $this->assertAuthenticatedAs($student);
        $this->assertNotEmpty(collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_')));
        $this->assertSame(1, AccessLink::query()->sole()->use_count);
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
        $this->post('/entrar', ['token' => $token])->assertSessionHasErrors(['token' => 'Este link não é mais válido. Peça um novo ao seu professor.']);

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
        $this->post('/entrar', ['token' => $new])->assertRedirect(route('my-week'));
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

    public function test_teacher_cannot_issue_link_for_account_with_password_but_admin_can(): void
    {
        $student = User::factory()->studentOf($this->classroom)->create();

        $this->actingAs($this->teacher)
            ->post("/admin/classes/adultos/membros/{$student->id}/link")
            ->assertSessionHasErrors('user');

        $this->actingAs(User::factory()->admin()->create())
            ->post("/admin/classes/adultos/membros/{$student->id}/link")
            ->assertSessionHasNoErrors();
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

        $this->actingAs($other)->post('/entrar', ['token' => $token])->assertRedirect(route('my-week'));

        $this->assertAuthenticatedAs($student);
    }

    public function test_managed_student_updates_profile_and_creates_password_only_with_email(): void
    {
        $student = User::factory()->managed()->studentOf($this->classroom)->create(['name' => 'João']);

        $this->actingAs($student)->patch('/conta/perfil', ['name' => 'João Pereira', 'email' => ''])->assertSessionHasNoErrors();
        $this->assertSame('João Pereira', $student->refresh()->name);

        $this->actingAs($student)->get('/conta/seguranca')->assertOk();

        $this->actingAs($student)
            ->put('/conta/senha', ['password' => 'nova-senha-123', 'password_confirmation' => 'nova-senha-123'])
            ->assertSessionHasErrors('password');

        $student->update(['email' => 'joao@example.com']);
        $this->actingAs($student)
            ->put('/conta/senha', ['password' => 'nova-senha-123', 'password_confirmation' => 'nova-senha-123'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($student->refresh()->isManaged());
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
