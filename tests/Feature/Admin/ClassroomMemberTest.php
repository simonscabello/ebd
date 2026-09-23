<?php

namespace Tests\Feature\Admin;

use App\Enums\ClassroomRole;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
