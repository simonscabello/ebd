<?php

namespace Tests\Feature\Mcp;

use App\Enums\ClassroomRole;
use App\Mcp\Servers\EbdServer;
use App\Mcp\Tools\AddManagedStudent;
use App\Mcp\Tools\MoveStudent;
use App\Mcp\Tools\UpdateStudent;
use App\Models\AccessLink;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alunos pelo servidor MCP: cadastrar, corrigir e mudar de classe. O link de
 * acesso nunca passa pelo agente.
 */
class McpStudentToolsTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::factory()->create(['slug' => 'jovens', 'name' => 'Jovens']);
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
    }

    public function test_adds_a_managed_student_without_issuing_a_link(): void
    {
        EbdServer::actingAs($this->teacher)->tool(AddManagedStudent::class, ['classroom' => 'jovens', 'name' => 'João Pedro', 'phone' => '(11) 98888-7777'])
            ->assertOk()
            ->assertSee(['João Pedro', '"is_managed":true', '/admin/classes/jovens/membros'])
            ->assertDontSee('/entrar#');

        $student = User::query()->where('name', 'João Pedro')->sole();
        $this->assertSame('11988887777', $student->phone);
        $this->assertSame(ClassroomRole::Student, $student->roleIn($this->classroom));
        $this->assertSame(0, AccessLink::query()->count());
        $this->assertSame(['name' => 'João Pedro', 'phone' => '11988887777'], AuditLog::query()->sole()->changes['after']);
    }

    public function test_similar_names_need_confirmation(): void
    {
        User::factory()->managed()->studentOf($this->classroom)->create(['name' => 'Joao Pedro Silva']);

        EbdServer::actingAs($this->teacher)->tool(AddManagedStudent::class, ['classroom' => 'jovens', 'name' => 'João Pedro'])
            ->assertHasErrors(['nome parecido', 'Joao Pedro Silva']);

        EbdServer::actingAs($this->teacher)->tool(AddManagedStudent::class, ['classroom' => 'jovens', 'name' => 'João Pedro', 'confirm_duplicate' => true])
            ->assertOk();
    }

    public function test_teacher_fixes_managed_accounts_but_not_accounts_with_password(): void
    {
        $managed = User::factory()->managed()->studentOf($this->classroom)->create(['name' => 'Marya', 'phone' => '11999990000']);
        User::factory()->studentOf($this->classroom)->create(['name' => 'Paulo']);

        EbdServer::actingAs($this->teacher)->tool(UpdateStudent::class, ['classroom' => 'jovens', 'student' => 'Marya', 'name' => 'Maria'])
            ->assertOk()
            ->assertSee('"name":"Maria"');
        $this->assertSame(['Maria', '11999990000'], [$managed->fresh()->name, $managed->fresh()->phone]);

        EbdServer::actingAs($this->teacher)->tool(UpdateStudent::class, ['classroom' => 'jovens', 'student' => 'Paulo', 'name' => 'Paulo Souza'])
            ->assertHasErrors(['senha própria']);

        EbdServer::actingAs(User::factory()->admin()->create())->tool(UpdateStudent::class, ['classroom' => 'jovens', 'student' => 'Paulo', 'name' => 'Paulo Souza'])
            ->assertOk();
    }

    public function test_move_student_requires_managing_both_classrooms(): void
    {
        $adults = Classroom::factory()->create(['slug' => 'adultos']);
        $student = User::factory()->managed()->studentOf($this->classroom)->create(['name' => 'Rute']);

        EbdServer::actingAs($this->teacher)->tools()->assertNotRegistered(MoveStudent::class);

        $both = User::factory()->teacherOf($this->classroom)->teacherOf($adults)->create();
        EbdServer::actingAs($both)->tools()->assertRegistered(MoveStudent::class);

        EbdServer::actingAs($both)->tool(MoveStudent::class, ['student' => 'Rute', 'from_classroom' => 'jovens', 'to_classroom' => 'adultos'])
            ->assertOk();

        $student->flushClassroomRoles();
        $this->assertFalse($student->isMemberOf($this->classroom));
        $this->assertSame(ClassroomRole::Student, $student->roleIn($adults));
        $this->assertSame(['classrooms' => ['jovens']], AuditLog::query()->sole()->changes['before']);
    }
}
