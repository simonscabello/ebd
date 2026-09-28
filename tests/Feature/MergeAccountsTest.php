<?php

namespace Tests\Feature;

use App\Models\AccessLink;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Aluno do link que saiu do app e criou outra conta: as duas viram uma só.
 */
class MergeAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_new_login_moves_to_the_account_with_the_class_history(): void
    {
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->teacherOf($classroom)->create();
        $original = User::factory()->managed()->studentOf($classroom)->create(['name' => 'Leidy Ane', 'phone' => '5527988107134']);
        $meeting = ClassMeeting::factory()->for($classroom)->on('2026-09-27')->held()->create();
        $meeting->attendances()->forceCreate(['user_id' => $original->id, 'recorded_by' => $teacher->id, 'created_at' => now()]);
        $this->actingAs($teacher)->post("/admin/classes/{$classroom->slug}/membros/{$original->id}/link");

        $duplicate = User::factory()->create(['name' => 'Leidy Ane Pralon', 'email' => 'leidy@example.com', 'password' => 'minha-senha1', 'phone' => null, 'birth_date' => null, 'gender' => null]);
        PushSubscription::factory()->for($duplicate)->create();

        $this->artisan('ebd:merge-accounts', ['keep' => $original->id, 'duplicate' => $duplicate->id])->assertSuccessful();

        $original->refresh();
        $this->assertSame(['Leidy Ane Pralon', 'leidy@example.com', '5527988107134'], [$original->name, $original->email, $original->phone]);
        $this->assertTrue(Hash::check('minha-senha1', $original->password));
        $this->assertTrue($original->isMemberOf($classroom));
        $this->assertSame(1, $original->pushSubscriptions()->count());
        $this->assertDatabaseHas('attendances', ['user_id' => $original->id]);
        $this->assertSame(0, AccessLink::query()->active()->count());

        // A outra conta continua existindo, mas vazia e sem login.
        $duplicate->refresh();
        $this->assertNull($duplicate->email);
        $this->assertNull($duplicate->password);

        Auth::logout();
        $this->post(route('login.store'), ['email' => 'leidy@example.com', 'password' => 'minha-senha1']);
        $this->assertAuthenticatedAs($original);
    }

    public function test_dry_run_changes_nothing_and_staff_is_refused(): void
    {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->studentOf($classroom)->create();
        $other = User::factory()->create(['email' => 'outra@example.com']);
        $teacher = User::factory()->teacherOf($classroom)->create();

        $this->artisan('ebd:merge-accounts', ['keep' => $student->id, 'duplicate' => $other->id, '--dry-run' => true])->assertSuccessful();
        $this->assertSame('outra@example.com', $other->fresh()->email);

        $this->artisan('ebd:merge-accounts', ['keep' => $student->id, 'duplicate' => $teacher->id])->assertFailed();
    }
}
