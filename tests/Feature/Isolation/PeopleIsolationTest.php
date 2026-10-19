<?php

namespace Tests\Feature\Isolation;

use App\Actions\Classrooms\MoveStudentToClassroom;
use App\Actions\Meetings\RecordAttendance;
use App\Models\Church;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Pessoas não atravessam igrejas: professor de uma igreja não acha, não
 * vincula, não emite link, não edita nem move quem é de outra.
 */
class PeopleIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classA;

    private Classroom $classB;

    private User $teacherA;

    private User $teacherB;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classA = Classroom::factory()->create(['slug' => 'turma-a']);
        $this->teacherA = User::factory()->teacherOf($this->classA)->create();

        $churchB = Church::factory()->create();
        $this->classB = Classroom::factory()->create(['slug' => 'turma-b', 'church_id' => $churchB->id]);
        $this->teacherB = User::factory()->teacherOf($this->classB)->create();
        $this->adminB = User::factory()->create();
        $churchB->admins()->attach($this->adminB);
    }

    public function test_adding_by_email_does_not_find_people_of_another_church(): void
    {
        $student = User::factory()->studentOf($this->classA)->create(['email' => 'ana@example.com']);
        $stranger = User::factory()->create(['email' => 'nova@example.com']);

        foreach (['ana@example.com', 'ninguem@example.com'] as $email) {
            $this->actingAs($this->teacherB)
                ->post('/admin/classes/turma-b/membros', ['email' => $email, 'role' => 'student'])
                ->assertSessionHasErrors(['email' => 'Não encontramos essa pessoa nas suas turmas. Envie um convite pelo link de acesso.']);
        }

        // Alguém de outra igreja também não pode virar professor aqui.
        $this->actingAs($this->adminB)
            ->post('/admin/classes/turma-b/membros', ['email' => 'ana@example.com', 'role' => 'teacher'])
            ->assertSessionHasErrors('email');

        // Uma conta sem vínculo nenhum pode ser nomeada professora.
        $this->actingAs($this->adminB)
            ->post('/admin/classes/turma-b/membros', ['email' => 'nova@example.com', 'role' => 'teacher'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($student->fresh()->isMemberOf($this->classB));
        $this->assertTrue($stranger->fresh()->isTeacherOf($this->classB));
    }

    public function test_a_person_in_two_churches_is_protected_from_the_other(): void
    {
        $student = User::factory()->studentOf($this->classA)->create(['name' => 'Ana', 'email' => 'ana@example.com']);
        $this->classB->members()->attach($student->id, ['role' => 'student']);

        foreach ([$this->teacherB, $this->adminB] as $user) {
            $this->actingAs($user)->post("/admin/classes/turma-b/membros/{$student->id}/link")->assertSessionHasErrors('user');
            $this->actingAs($user)->put("/admin/classes/turma-b/alunos/{$student->id}", ['name' => 'Trocado', 'email' => 'outro@example.com'])
                ->assertSessionHasErrors();
        }

        $this->assertSame(['Ana', 'ana@example.com'], [$student->fresh()->name, $student->fresh()->email]);
        $this->assertSame(0, $student->accessLinks()->count());

        // Quem gerencia todas as turmas dela continua podendo.
        $this->classB->members()->detach($student->id);
        $this->actingAs($this->teacherA)->post("/admin/classes/turma-a/membros/{$student->id}/link")->assertSessionHasNoErrors();
    }

    public function test_moving_between_churches_is_refused_even_for_an_admin_of_both(): void
    {
        $student = User::factory()->studentOf($this->classA)->create();

        $this->expectException(ValidationException::class);
        app(MoveStudentToClassroom::class)->handle($student, $this->classA, $this->classB);
    }

    public function test_an_account_with_history_elsewhere_cannot_be_captured(): void
    {
        // Ex-aluna de A: tem senha, saiu de todas as turmas, mas a presença ficou.
        $former = User::factory()->studentOf($this->classA)->create(['email' => 'ex@example.com']);
        $meeting = ClassMeeting::factory()->for($this->classA)->on(now()->previous('Sunday')->toDateString())->create();
        app(RecordAttendance::class)->handle($meeting, [$former->id], 0, $this->teacherA);
        $this->classA->members()->detach($former->id);

        $this->actingAs($this->adminB)
            ->post('/admin/classes/turma-b/membros', ['email' => 'ex@example.com', 'role' => 'teacher'])
            ->assertSessionHasErrors(['email' => 'Não encontramos uma conta disponível com este e-mail.']);
        $this->assertFalse($former->fresh()->isMemberOf($this->classB));
    }

    public function test_a_teacher_with_a_password_is_not_demoted_to_student(): void
    {
        $orphan = User::factory()->create(['email' => 'orfa@example.com']);

        $this->actingAs($this->adminB)->post('/admin/classes/turma-b/membros', ['email' => 'orfa@example.com', 'role' => 'teacher'])->assertSessionHasNoErrors();
        $this->actingAs($this->adminB)->post('/admin/classes/turma-b/membros', ['email' => 'orfa@example.com', 'role' => 'student'])->assertSessionHasErrors('email');

        $this->assertTrue($orphan->fresh()->isTeacherOf($this->classB));
        $this->actingAs($this->adminB)->post("/admin/classes/turma-b/membros/{$orphan->id}/link")->assertSessionHasErrors('user');
        $this->assertSame(0, $orphan->accessLinks()->count());
    }

    public function test_coordinators_follow_the_same_church_rule(): void
    {
        $student = User::factory()->studentOf($this->classA)->create(['email' => 'ana@example.com']);
        $student->accessLinks()->forceCreate(['classroom_id' => $this->classA->id, 'token_hash' => str_repeat('b', 64)]);

        $this->actingAs($this->adminB)
            ->post("/admin/programas/{$this->classB->program_id}/coordenadores", ['email' => 'ana@example.com'])
            ->assertSessionHasErrors(['email' => 'Não encontramos uma conta disponível com este e-mail.']);

        $this->assertFalse($student->fresh()->canAccessAdmin());
        $this->assertSame(1, $student->accessLinks()->active()->count());
    }

    public function test_removing_someone_who_is_not_there_reveals_nothing(): void
    {
        $stranger = User::factory()->studentOf($this->classA)->create(['name' => 'Fulana Secreta']);

        $this->actingAs($this->adminB)->delete("/admin/classes/turma-b/membros/{$stranger->id}")->assertNotFound();
        $this->actingAs($this->adminB)->delete("/admin/programas/{$this->classB->program_id}/coordenadores/{$stranger->id}")->assertNotFound();
    }

    public function test_a_teacher_of_a_who_creates_a_church_cannot_pull_students_of_a(): void
    {
        $student = User::factory()->studentOf($this->classA)->create(['email' => 'ana@example.com']);
        $own = Church::factory()->create();
        $own->admins()->attach($this->teacherA);
        $ownClass = Classroom::factory()->create(['slug' => 'turma-propria', 'church_id' => $own->id]);

        $this->actingAs($this->teacherA)
            ->post('/admin/classes/turma-propria/membros', ['email' => 'ana@example.com', 'role' => 'student'])
            ->assertSessionHasErrors('email');

        $this->assertFalse($student->fresh()->isMemberOf($ownClass));
    }
}
