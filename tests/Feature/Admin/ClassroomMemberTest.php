<?php

namespace Tests\Feature\Admin;

use App\Enums\ClassroomRole;
use App\Models\AccessLink;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Support\SessionKey;
use Tests\TestCase;

class ClassroomMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_adds_existing_user_as_student(): void
    {
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->teacherOf($classroom)->create();
        $user = User::factory()->create(['email' => 'maria@example.com']);

        $this->actingAs($teacher)
            ->post("/admin/classes/{$classroom->slug}/membros", ['email' => 'MARIA@example.com', 'role' => 'student'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ClassroomRole::Student, $user->roleIn($classroom));
    }

    public function test_only_admin_can_promote_teachers_or_remove_them(): void
    {
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->teacherOf($classroom)->create();
        $colleague = User::factory()->teacherOf($classroom)->create();
        $user = User::factory()->create();

        $this->actingAs($teacher)
            ->post("/admin/classes/{$classroom->slug}/membros", ['email' => $user->email, 'role' => 'teacher'])
            ->assertForbidden();

        $this->actingAs($teacher)
            ->delete("/admin/classes/{$classroom->slug}/membros/{$colleague->id}")
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->post("/admin/classes/{$classroom->slug}/membros", ['email' => $user->email, 'role' => 'teacher'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->isTeacherOf($classroom));
    }

    public function test_teacher_cannot_turn_a_colleague_into_a_student_by_email(): void
    {
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->teacherOf($classroom)->create();
        $colleague = User::factory()->teacherOf($classroom)->create();

        $this->actingAs($teacher)
            ->post("/admin/classes/{$classroom->slug}/membros", ['email' => $colleague->email, 'role' => 'student'])
            ->assertSessionHasErrors('email');

        $this->assertTrue($colleague->isTeacherOf($classroom));
    }

    public function test_removing_a_student_revokes_the_link_and_forgets_devices_of_link_only_accounts(): void
    {
        config(['session.driver' => 'database']);
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->teacherOf($classroom)->create();
        $student = User::factory()->managed()->studentOf($classroom)->create();

        $this->actingAs($teacher)->post("/admin/classes/{$classroom->slug}/membros/{$student->id}/link");
        DB::table('sessions')->insert(['id' => 'xyz', 'user_id' => $student->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($teacher)
            ->delete("/admin/classes/{$classroom->slug}/membros/{$student->id}")
            ->assertSessionHasNoErrors();

        $this->assertFalse($student->isMemberOf($classroom));
        $this->assertSame(0, AccessLink::query()->active()->count());
        $this->assertDatabaseMissing('sessions', ['user_id' => $student->id]);
    }

    public function test_unknown_email_returns_friendly_error(): void
    {
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->teacherOf($classroom)->create();

        $this->actingAs($teacher)
            ->post("/admin/classes/{$classroom->slug}/membros", ['email' => 'ninguem@example.com', 'role' => 'student'])
            ->assertSessionHasErrors('email');
    }

    public function test_user_can_have_different_roles_in_different_classrooms(): void
    {
        $jovens = Classroom::factory()->create();
        $adultos = Classroom::factory()->create();
        $user = User::factory()->teacherOf($jovens)->studentOf($adultos)->create();

        $this->assertTrue($user->isTeacherOf($jovens));
        $this->assertFalse($user->isTeacherOf($adultos));
        $this->assertTrue($user->isMemberOf($adultos));
        $this->assertTrue($user->canAccessAdmin());
        $this->assertSame([$jovens->id], $user->manageableClassroomIds());
    }

    /**
     * Remove o aluno e devolve a URL do "Desfazer" que veio no toast.
     */
    private function removeAndGetUndoUrl(User $teacher, Classroom $classroom, User $student): string
    {
        $response = $this->actingAs($teacher)->delete("/admin/classes/{$classroom->slug}/membros/{$student->id}");

        $toast = session(SessionKey::FLASH_DATA)['toast'];
        $this->assertSame('Desfazer', $toast['action']['label']);
        $response->assertRedirect();

        return $toast['action']['url'];
    }

    public function test_undo_brings_the_student_back_with_the_original_join_date(): void
    {
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->teacherOf($classroom)->create();
        $student = User::factory()->studentOf($classroom)->create();
        DB::table('classroom_user')->where('user_id', $student->id)->update(['created_at' => '2026-03-01 12:00:00']);

        $url = $this->removeAndGetUndoUrl($teacher, $classroom, $student);
        $this->assertFalse($student->flushClassroomRoles()->isMemberOf($classroom));

        $this->actingAs($teacher)->post($url)
            ->assertRedirect("/admin/classes/{$classroom->slug}/alunos/{$student->id}");

        $this->assertSame(ClassroomRole::Student, $student->flushClassroomRoles()->roleIn($classroom));
        $this->assertSame(
            '2026-03-01 12:00:00',
            (string) DB::table('classroom_user')->where('user_id', $student->id)->value('created_at'),
        );
    }

    public function test_undo_link_expires_and_cannot_be_tampered_with(): void
    {
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->teacherOf($classroom)->create();
        $student = User::factory()->studentOf($classroom)->create();

        $url = $this->removeAndGetUndoUrl($teacher, $classroom, $student);

        $this->actingAs($teacher)->post(str_replace('role=student', 'role=teacher', $url))->assertForbidden();

        $this->travel(3)->minutes();
        $this->actingAs($teacher)->post($url)->assertForbidden();

        $this->assertFalse($student->flushClassroomRoles()->isMemberOf($classroom));
    }
}
