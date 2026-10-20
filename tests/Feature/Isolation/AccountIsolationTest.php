<?php

namespace Tests\Feature\Isolation;

use App\Actions\Access\IssueAccessLink;
use App\Enums\Badge;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use App\Queries\StudentProgressQuery;
use App\Support\ChurchCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Contas não atravessam igrejas: nomear quem gerencia derruba as sessões do
 * link, bloquear acesso e desfazer remoção seguem as mesmas regras de
 * vincular, e nada da igreja de outra pessoa aparece para quem não é dela.
 */
class AccountIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classA;

    private Classroom $classB;

    private Church $churchB;

    private User $teacherA;

    private User $adminA;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);

        $this->classA = Classroom::factory()->create(['slug' => 'turma-a']);
        $this->teacherA = User::factory()->teacherOf($this->classA)->create();
        $this->adminA = User::factory()->create();
        $this->classA->church->admins()->attach($this->adminA);

        $this->churchB = Church::factory()->create();
        $this->classB = Classroom::factory()->create(['slug' => 'turma-b', 'church_id' => $this->churchB->id]);
        $this->adminB = User::factory()->create();
        $this->churchB->admins()->attach($this->adminB);
    }

    /**
     * Aluno que já entrou pelo link: tem link ativo, "lembrar de mim" e uma sessão aberta.
     */
    private function studentWithLinkSession(Classroom $classroom, User $issuer, array $attributes = []): User
    {
        $student = User::factory()->managed()->studentOf($classroom)->create($attributes);
        app(IssueAccessLink::class)->handle($student, $classroom, $issuer);
        DB::table('sessions')->insert(['id' => 'sessao-'.$student->id, 'user_id' => $student->id, 'payload' => '', 'last_activity' => time()]);

        return $student->refresh();
    }

    private function assertLinkSessionsGone(User $student, string $rememberToken): void
    {
        $this->assertSame(0, $student->accessLinks()->active()->count());
        $this->assertNotSame($rememberToken, $student->refresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['user_id' => $student->id]);
    }

    public function test_naming_a_teacher_also_ends_the_sessions_opened_by_the_link(): void
    {
        $student = $this->studentWithLinkSession($this->classA, $this->teacherA, ['email' => 'ana@example.com']);
        $rememberToken = (string) $student->remember_token;

        $this->actingAs($this->adminA)
            ->post('/admin/classes/turma-a/membros', ['email' => 'ana@example.com', 'role' => 'teacher'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($student->fresh()->isTeacherOf($this->classA));
        $this->assertLinkSessionsGone($student, $rememberToken);
    }

    public function test_naming_a_coordinator_also_ends_the_sessions_opened_by_the_link(): void
    {
        $student = $this->studentWithLinkSession($this->classA, $this->teacherA, ['email' => 'bia@example.com']);
        $rememberToken = (string) $student->remember_token;
        $program = $this->classA->program;

        $this->actingAs($this->adminA)
            ->post("/admin/programas/{$program->id}/coordenadores", ['email' => 'bia@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($student->fresh()->isCoordinatorOf($program));
        $this->assertLinkSessionsGone($student, $rememberToken);
    }

    public function test_naming_someone_who_never_had_a_link_does_not_log_them_out(): void
    {
        $person = User::factory()->create(['email' => 'nova@example.com']);
        $rememberToken = (string) $person->remember_token;
        DB::table('sessions')->insert(['id' => 'sessao-nova', 'user_id' => $person->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->adminA)
            ->post('/admin/classes/turma-a/membros', ['email' => 'nova@example.com', 'role' => 'teacher'])
            ->assertSessionHasNoErrors();

        $this->assertSame($rememberToken, $person->refresh()->remember_token);
        $this->assertDatabaseHas('sessions', ['user_id' => $person->id]);
    }

    public function test_blocking_access_needs_someone_who_follows_every_classroom_of_the_person(): void
    {
        $otherA = Classroom::factory()->create(['slug' => 'turma-a2', 'church_id' => $this->classA->church_id]);
        $student = $this->studentWithLinkSession($this->classA, $this->adminA);
        $otherA->members()->attach($student->id, ['role' => 'student']);
        $rememberToken = (string) $student->remember_token;

        // O professor de A não acompanha a outra turma dela: não derruba o acesso.
        $this->actingAs($this->teacherA)
            ->delete("/admin/classes/turma-a/membros/{$student->id}/link")
            ->assertSessionHasErrors('user');

        $this->assertSame(1, $student->accessLinks()->active()->count());
        $this->assertSame($rememberToken, $student->refresh()->remember_token);
        $this->assertDatabaseHas('sessions', ['user_id' => $student->id]);

        // Quem acompanha todas as turmas dela continua podendo.
        $this->actingAs($this->adminA)
            ->delete("/admin/classes/turma-a/membros/{$student->id}/link")
            ->assertSessionHasNoErrors();
        $this->assertLinkSessionsGone($student, $rememberToken);
    }

    public function test_undoing_a_removal_is_refused_when_the_person_joined_another_church(): void
    {
        $student = User::factory()->studentOf($this->classA)->create();
        $since = now()->subMonth()->getTimestamp();

        $this->actingAs($this->teacherA)->delete("/admin/classes/turma-a/membros/{$student->id}");
        // Nos 2 minutos do "Desfazer", outra igreja vincula a pessoa.
        $this->classB->members()->attach($student->id, ['role' => 'student']);

        $url = URL::temporarySignedRoute('admin.classrooms.members.restore', now()->addMinutes(2), [
            'classroom' => $this->classA, 'user' => $student, 'role' => 'student', 'since' => $since,
        ]);
        $this->actingAs($this->teacherA)->post($url)->assertRedirect();

        $this->assertFalse($student->fresh()->isMemberOf($this->classA));
    }

    public function test_the_old_series_report_address_does_not_reveal_another_church(): void
    {
        $series = Series::factory()->for($this->classB)->create();

        $this->actingAs($this->adminA)->get("/admin/series/{$series->id}/relatorio")->assertNotFound();
        $this->actingAs($this->adminB)->get("/admin/series/{$series->id}/relatorio")
            ->assertRedirect("/admin/classes/turma-b/relatorio?serie={$series->id}");
    }

    public function test_progress_shows_only_badges_and_streak_of_the_church_of_the_classroom(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $student = User::factory()->studentOf($this->classA)->create();
        $this->classB->members()->attach($student->id, ['role' => 'student']);

        $seriesA = Series::factory()->for($this->classA)->create(['title' => 'Série de A']);
        $seriesB = Series::factory()->for($this->classB)->create(['title' => 'Série de B']);

        foreach ([$seriesA, $seriesB, null] as $series) {
            DB::table('user_badges')->insert([
                'user_id' => $student->id,
                'badge' => $series === null ? Badge::Streak7->value : Badge::FaithfulReader->value,
                'series_id' => $series?->id,
                'awarded_at' => now(),
            ]);
        }

        // Leu ontem e hoje, só numa lição da igreja B.
        $lessonB = Lesson::factory()->for($this->classB)->published()->create();
        foreach ([1, 0] as $daysAgo) {
            DB::table('reading_checkins')->insert([
                'user_id' => $student->id,
                'lesson_id' => $lessonB->id,
                'weekday' => $daysAgo + 1,
                'read_on' => ChurchCalendar::today()->subDays($daysAgo)->toDateString(),
                'created_at' => now(),
            ]);
        }

        $progressA = app(StudentProgressQuery::class)->for($student, $this->classA);
        $this->assertEqualsCanonicalizing(['Série de A', null], $progressA['badges']->pluck('series')->all());
        $this->assertSame(0, $progressA['streak']['best']);

        $progressB = app(StudentProgressQuery::class)->for($student, $this->classB);
        $this->assertEqualsCanonicalizing(['Série de B', null], $progressB['badges']->pluck('series')->all());
        $this->assertSame(2, $progressB['streak']['best']);
    }

    public function test_an_account_without_church_sees_nothing_of_the_first_church(): void
    {
        Lesson::factory()->for($this->classA)->published()->create(['title' => 'Pública A']);
        $loner = User::factory()->create();

        $library = $this->actingAs($loner)->get('/biblioteca');
        $this->assertSame([], collect($library->viewData('page')['props']['results']['data'])->pluck('title')->all());
        $this->assertSame([], collect($library->viewData('page')['props']['classrooms'])->all());
        $library->assertInertia(fn (Assert $page) => $page->where('church.name', config('ebd.church_name')));
    }

    public function test_a_coordinator_only_of_another_church_has_that_church_as_current(): void
    {
        $coordinator = User::factory()->create();
        $this->classB->program->coordinators()->attach($coordinator);

        $this->assertSame($this->churchB->id, $coordinator->currentChurch()?->id);
        $this->actingAs($coordinator)->get('/biblioteca')
            ->assertInertia(fn (Assert $page) => $page->where('church.name', $this->churchB->name));
    }

    public function test_move_targets_are_only_classrooms_of_the_same_church(): void
    {
        $otherA = Classroom::factory()->create(['slug' => 'turma-a2', 'church_id' => $this->classA->church_id]);
        $official = Church::factory()->create();
        $official->forceFill(['is_official' => true])->save();
        Classroom::factory()->create(['church_id' => $official->id]);

        // Quem administra as duas igrejas (e a biblioteca) só move dentro da igreja da turma.
        $this->churchB->admins()->attach($this->adminA);
        $official->admins()->attach($this->adminA);
        $student = User::factory()->studentOf($this->classA)->create();

        $this->actingAs($this->adminA)->get("/admin/classes/turma-a/alunos/{$student->id}")
            ->assertInertia(fn (Assert $page) => $page->where('moveTargets', [['slug' => 'turma-a2', 'name' => $otherA->name]]));
    }

    public function test_new_lesson_does_not_preselect_a_classroom_of_another_church(): void
    {
        $this->actingAs($this->adminA)->get("/admin/licoes/criar?classe={$this->classB->id}")
            ->assertInertia(fn (Assert $page) => $page->where('defaults.classroom_id', $this->classA->id));

        $this->actingAs($this->adminB)->get("/admin/licoes/criar?classe={$this->classB->id}")
            ->assertInertia(fn (Assert $page) => $page->where('defaults.classroom_id', $this->classB->id));
    }
}
