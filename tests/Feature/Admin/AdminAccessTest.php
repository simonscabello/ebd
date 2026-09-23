<?php

namespace Tests\Feature\Admin;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_students_cannot_access_admin_area(): void
    {
        $student = User::factory()->studentOf(Classroom::factory()->create())->create();

        $this->actingAs($student)->get('/admin')->assertForbidden();
        $this->actingAs($student)->get('/admin/licoes')->assertForbidden();
    }

    public function test_teachers_and_admins_can_access_admin_area(): void
    {
        $teacher = User::factory()->teacherOf(Classroom::factory()->create())->create();

        $this->actingAs($teacher)->get('/admin')->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    }

    public function test_only_admins_manage_classrooms(): void
    {
        $teacher = User::factory()->teacherOf(Classroom::factory()->create())->create();

        $this->actingAs($teacher)->get('/admin/classes')->assertForbidden();
        $this->actingAs($teacher)->post('/admin/classes', ['name' => 'Casais'])->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/classes', ['name' => 'Casais', 'is_active' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('classrooms', ['name' => 'Casais', 'slug' => 'casais']);
    }
}
