<?php

namespace Tests\Feature\Admin;

use App\Actions\Meetings\RecordAttendance;
use App\Enums\Gender;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\StudentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Alunos da classe: a lista, a ficha, as anotações do professor e as ações.
 */
class StudentPagesTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'America/Sao_Paulo']);
        // Domingo, 27/09/2026, 10h em São Paulo.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 9, 27)->setTime(10, 0));

        $this->classroom = Classroom::factory()->create(['slug' => 'jovens', 'name' => 'Jovens']);
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
    }

    private function student(string $name, string $joinedOn = '2026-08-01', array $attributes = []): User
    {
        $user = User::factory()->create(['name' => $name, ...$attributes]);
        $this->classroom->members()->attach($user->id, ['role' => 'student', 'created_at' => "{$joinedOn} 15:00:00", 'updated_at' => "{$joinedOn} 15:00:00"]);

        return $user;
    }

    /**
     * @param  list<User>  $present
     */
    private function sunday(string $date, array $present): ClassMeeting
    {
        $meeting = ClassMeeting::factory()->for($this->classroom)->on($date)->create();
        app(RecordAttendance::class)->handle($meeting, array_map(fn (User $u) => $u->id, $present), 0, $this->teacher);

        return $meeting;
    }

    public function test_list_shows_frequency_and_flags(): void
    {
        $ana = $this->student('Ana', attributes: ['birth_date' => '2005-09-27']);
        $this->student('Bia');
        $this->student('Caio', '2026-09-24');
        $link = User::factory()->managed()->create(['name' => 'Davi']);
        $this->classroom->members()->attach($link->id, ['role' => 'student', 'created_at' => '2026-08-01 15:00:00', 'updated_at' => '2026-08-01 15:00:00']);

        $this->sunday('2026-09-13', [$ana]);
        $this->sunday('2026-09-20', [$ana]);

        $this->actingAs($this->teacher)->get('/admin/classes/jovens/alunos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/classrooms/students/index')
                ->has('students', 4)
                ->where('students.0.name', 'Ana')
                ->where('students.0.frequency.present', 2)
                ->where('students.0.frequency.expected', 2)
                ->where('students.0.birthday', 'today')
                ->where('students.0.access', 'ok')
                ->where('students.1.name', 'Bia')
                ->where('students.1.needs_attention', true)
                ->where('students.2.name', 'Caio')
                ->where('students.2.is_new', true)
                ->where('students.3.name', 'Davi')
                ->where('students.3.access', 'never_entered'));
    }

    public function test_student_page_has_history_notes_and_access(): void
    {
        $ana = $this->student('Ana', attributes: ['birth_date' => '2004-05-17', 'gender' => Gender::Female]);
        $this->sunday('2026-09-13', []);
        $this->sunday('2026-09-20', [$ana]);

        $note = new StudentNote(['body' => 'Mudou de turno no trabalho']);
        $note->classroom_id = $this->classroom->id;
        $note->user_id = $ana->id;
        $note->author_id = $this->teacher->id;
        $note->save();

        $this->actingAs($this->teacher)->get("/admin/classes/jovens/alunos/{$ana->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/classrooms/students/show')
                ->where('student.age', 22)
                ->where('student.gender_label', 'Feminino')
                ->where('stats.frequency.present', 1)
                ->where('stats.frequency.expected', 2)
                ->where('sundays.0.held_on', '2026-09-20')
                ->where('sundays.0.present', true)
                ->where('sundays.1.present', false)
                ->where('notes.0.body', 'Mudou de turno no trabalho')
                ->where('notes.0.can_edit', true));
    }

    public function test_notes_are_private_to_the_class_teachers(): void
    {
        $ana = $this->student('Ana');
        $colleague = User::factory()->teacherOf($this->classroom)->create();
        $outsider = User::factory()->teacherOf(Classroom::factory()->create())->create();

        $this->actingAs($this->teacher)
            ->post("/admin/classes/jovens/alunos/{$ana->id}/anotacoes", ['body' => 'Visitar em outubro'])
            ->assertSessionHasNoErrors();
        $note = StudentNote::query()->sole();

        // Outro professor da classe lê, mas só quem escreveu (ou a administração) edita.
        $this->actingAs($colleague)->put("/admin/anotacoes/{$note->id}", ['body' => 'x'])->assertForbidden();
        $this->actingAs($colleague)->delete("/admin/anotacoes/{$note->id}")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->put("/admin/anotacoes/{$note->id}", ['body' => 'Visitar em novembro'])->assertSessionHasNoErrors();
        $this->assertSame('Visitar em novembro', $note->fresh()->body);

        $this->actingAs($outsider)->post("/admin/classes/jovens/alunos/{$ana->id}/anotacoes", ['body' => 'x'])->assertForbidden();
        $this->actingAs($ana)->get("/admin/classes/jovens/alunos/{$ana->id}")->assertForbidden();

        // Nada das anotações chega às telas do aluno.
        $this->actingAs($ana)->get('/meu-progresso')->assertOk()->assertDontSee('Visitar em novembro');

        $this->actingAs($this->teacher)->delete("/admin/anotacoes/{$note->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($note);
    }

    public function test_teacher_edits_student_data_but_not_the_login_of_an_account_with_password(): void
    {
        $paulo = $this->student('Paulo', attributes: ['email' => 'paulo@example.com']);
        $link = User::factory()->managed()->create(['name' => 'Davi']);
        $this->classroom->members()->attach($link->id, ['role' => 'student']);

        $this->actingAs($this->teacher)
            ->put("/admin/classes/jovens/alunos/{$paulo->id}", ['name' => 'Paulo Souza', 'phone' => '(27) 99999-1111', 'birth_date' => '2003-01-02', 'gender' => 'male'])
            ->assertSessionHasNoErrors();

        $paulo->refresh();
        $this->assertSame(['Paulo Souza', '5527999991111', '2003-01-02', Gender::Male], [$paulo->name, $paulo->phone, $paulo->birth_date->toDateString(), $paulo->gender]);

        $this->actingAs($this->teacher)
            ->put("/admin/classes/jovens/alunos/{$paulo->id}", ['name' => 'Paulo Souza', 'email' => 'outro@example.com'])
            ->assertSessionHasErrors('email');
        $this->assertSame('paulo@example.com', $paulo->fresh()->email);

        $this->actingAs($this->teacher)
            ->put("/admin/classes/jovens/alunos/{$link->id}", ['name' => 'Davi', 'email' => 'DAVI@example.com'])
            ->assertSessionHasNoErrors();
        $this->assertSame('davi@example.com', $link->fresh()->email);
    }

    public function test_moving_a_student_needs_both_classrooms(): void
    {
        $ana = $this->student('Ana');
        $adults = Classroom::factory()->create(['slug' => 'adultos']);
        $other = Classroom::factory()->create(['slug' => 'casais']);
        $this->teacher->classrooms()->attach($adults, ['role' => 'teacher']);

        $this->actingAs($this->teacher)->post("/admin/classes/jovens/alunos/{$ana->id}/mover", ['to' => 'casais'])->assertForbidden();

        $this->actingAs($this->teacher)
            ->post("/admin/classes/jovens/alunos/{$ana->id}/mover", ['to' => 'adultos'])
            ->assertRedirect('/admin/classes/jovens/alunos');

        $ana->flushClassroomRoles();
        $this->assertFalse($ana->isMemberOf($this->classroom));
        $this->assertTrue($ana->isMemberOf($adults));
        $this->assertFalse($ana->isMemberOf($other));
    }

    public function test_adding_a_similar_name_asks_for_confirmation(): void
    {
        $this->student('João Pedro Silva');

        $this->actingAs($this->teacher)
            ->post('/admin/classes/jovens/alunos', ['name' => 'joao pedro'])
            ->assertSessionHasErrors('duplicate');
        $this->assertSame(1, $this->classroom->students()->count());

        $this->actingAs($this->teacher)
            ->post('/admin/classes/jovens/alunos', ['name' => 'joao pedro', 'confirm_duplicate' => true])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $this->classroom->students()->count());
    }

    public function test_removing_goes_back_to_the_list_and_old_address_redirects(): void
    {
        $ana = $this->student('Ana');

        $this->actingAs($this->teacher)
            ->delete("/admin/classes/jovens/membros/{$ana->id}")
            ->assertRedirect('/admin/classes/jovens/alunos');

        $this->actingAs($this->teacher)
            ->get('/admin/classes/jovens/membros')
            ->assertStatus(301)
            ->assertRedirect('/admin/classes/jovens/alunos');
    }
}
