<?php

namespace Tests\Feature\Admin;

use App\Actions\Meetings\RecordAttendance;
use App\Actions\Meetings\SyncUpcomingMeeting;
use App\Enums\Weekday;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Cada turma escolhe o dia da semana do encontro (padrão domingo).
 */
class ClassroomScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'America/Sao_Paulo']);
        // Domingo, 27/09/2026, 10h em São Paulo.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 9, 27)->setTime(10, 0));
    }

    private function payload(array $data = []): array
    {
        return ['name' => 'Turma Nova de Teste', 'is_active' => true, ...$data];
    }

    public function test_new_classrooms_meet_on_sunday_by_default(): void
    {
        $this->assertSame(Weekday::Sunday, Classroom::factory()->create()->refresh()->meeting_weekday);

        $this->actingAs(User::factory()->admin()->create())->post('/admin/classes', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(Weekday::Sunday, Classroom::query()->where('name', 'Turma Nova de Teste')->sole()->meeting_weekday);
    }

    public function test_admin_sets_the_meeting_weekday(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/classes', $this->payload(['meeting_weekday' => 3]))->assertSessionHasNoErrors();
        $this->assertSame(Weekday::Wednesday, Classroom::query()->where('name', 'Turma Nova de Teste')->sole()->meeting_weekday);

        $this->actingAs($admin)->post('/admin/classes', $this->payload(['name' => 'Outra', 'meeting_weekday' => 8]))->assertSessionHasErrors('meeting_weekday');
    }

    public function test_changing_the_day_moves_planned_future_meetings_within_their_week(): void
    {
        $classroom = Classroom::factory()->create(['slug' => 'jovens']);
        $teacher = User::factory()->teacherOf($classroom)->create();
        $student = User::factory()->studentOf($classroom)->create();

        $held = ClassMeeting::factory()->for($classroom)->on('2026-09-20')->create();
        app(RecordAttendance::class)->handle($held, [$student->id], 0, $teacher);
        $a = Lesson::factory()->for($classroom)->published()->on('2026-10-04')->create();
        $cancelled = ClassMeeting::factory()->for($classroom)->on('2026-10-11')->cancelled()->create();
        $b = Lesson::factory()->for($classroom)->published()->on('2026-10-18')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put("/admin/classes/{$classroom->slug}", ['name' => $classroom->name, 'is_active' => true, 'meeting_weekday' => 3])
            ->assertSessionHasNoErrors();

        $dates = fn (int $lessonId) => ClassMeeting::query()->where('lesson_id', $lessonId)->pluck('held_on')->map->toDateString()->all();
        $this->assertSame(['2026-09-30'], $dates($a->id));
        // Só a lição atual fica na agenda: o encontro de b anda de dia, sem lição.
        $this->assertSame([], $dates($b->id));
        $this->assertTrue(ClassMeeting::query()->whereBelongsTo($classroom)->whereDate('held_on', '2026-10-14')->exists());
        $this->assertSame('2026-09-20', $held->refresh()->held_on->toDateString());
        // O "sem EBD" fica na data em que foi marcado: o cancelamento foi para aquele dia.
        $this->assertSame('2026-10-11', $cancelled->refresh()->held_on->toDateString());
        $this->assertSame('2026-09-30', $a->refresh()->scheduled_for->toDateString());
        $this->assertSame(Weekday::Wednesday, $classroom->refresh()->meeting_weekday);
    }

    public function test_teacher_cannot_change_the_meeting_day(): void
    {
        $classroom = Classroom::factory()->create(['slug' => 'jovens']);
        $teacher = User::factory()->teacherOf($classroom)->create();

        $this->actingAs($teacher)
            ->put('/admin/classes/jovens', ['name' => $classroom->name, 'meeting_weekday' => 3])
            ->assertForbidden();

        $this->assertSame(Weekday::Sunday, $classroom->refresh()->meeting_weekday);
    }

    private function changeTo(Classroom $classroom, int $weekday, ?string $name = null): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->put("/admin/classes/{$classroom->slug}", ['name' => $name ?? $classroom->name, 'is_active' => true, 'meeting_weekday' => $weekday]);
    }

    public function test_the_next_meeting_after_changing_the_day_is_not_in_the_past(): void
    {
        $classroom = Classroom::factory()->create(['slug' => 'jovens']);
        $classroom->forceFill(['current_lesson_id' => Lesson::factory()->for($classroom)->create()->id])->save();
        for ($day = now()->parse('2026-08-02'); $day->lte(now()->parse('2026-09-20')); $day = $day->addWeek()) {
            ClassMeeting::factory()->for($classroom)->on($day->toDateString())->create(['status' => 'held']);
        }

        $this->changeTo($classroom, 3)->assertRedirect();
        app(SyncUpcomingMeeting::class)->handle($classroom->refresh());

        $this->assertSame(0, ClassMeeting::query()->whereBelongsTo($classroom)->whereDate('held_on', '<', '2026-09-27')
            ->whereRaw('extract(isodow from held_on) = 3')->count());
        $this->assertSame('2026-09-30', ClassMeeting::query()->whereBelongsTo($classroom)->whereRaw('extract(isodow from held_on) = 3')->orderBy('held_on')->value('held_on')->toDateString());
    }

    public function test_changing_the_day_midweek_pushes_this_weeks_meeting_and_the_next_ones_forward(): void
    {
        // Quinta, 22/10: a quarta desta semana já passou. O encontro de
        // domingo vai para a próxima quarta e os seguintes também andam para a
        // frente, sem colidir e mantendo uma semana entre as lições.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 10, 22)->setTime(10, 0));
        $classroom = Classroom::factory()->create(['slug' => 'jovens']);
        $a = Lesson::factory()->for($classroom)->published()->on('2026-10-25')->create();
        $b = Lesson::factory()->for($classroom)->published()->on('2026-11-01')->create();
        ClassMeeting::factory()->for($classroom)->on('2026-11-08')->create();

        $this->changeTo($classroom, 3, 'Jovens novo')->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['2026-10-28', '2026-11-04', '2026-11-11'], ClassMeeting::query()->whereBelongsTo($classroom)->orderBy('held_on')->pluck('held_on')->map->toDateString()->all());
        $this->assertSame('2026-10-28', $a->refresh()->scheduled_for->toDateString());
        $this->assertNull($b->refresh()->scheduled_for);
        $this->assertSame(['Jovens novo', Weekday::Wednesday], [$classroom->refresh()->name, $classroom->meeting_weekday]);
    }

    public function test_changing_the_day_skips_a_meeting_whose_new_date_is_taken(): void
    {
        $classroom = Classroom::factory()->create(['slug' => 'jovens']);
        ClassMeeting::factory()->for($classroom)->on('2026-10-04')->create();
        ClassMeeting::factory()->for($classroom)->on('2026-09-30')->create(['title' => 'Encontro extra']);

        $this->changeTo($classroom, 3)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['2026-09-30', '2026-10-04'], ClassMeeting::query()->whereBelongsTo($classroom)->orderBy('held_on')->pluck('held_on')->map->toDateString()->all());
    }

    public function test_a_meeting_left_on_the_old_day_can_still_be_edited(): void
    {
        $classroom = Classroom::factory()->create(['slug' => 'jovens']);
        $teacher = User::factory()->teacherOf($classroom)->create();
        $past = ClassMeeting::factory()->for($classroom)->on('2026-09-20')->create(['status' => 'held']);
        $this->changeTo($classroom, 3);

        $this->actingAs($teacher)->put("/admin/encontros/{$past->id}", ['held_on' => '2026-09-20', 'title' => 'Culto especial'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Culto especial', $past->refresh()->title);
    }

    public function test_restoring_a_cancelled_meeting_off_the_classroom_day_is_refused(): void
    {
        $classroom = Classroom::factory()->create(['slug' => 'jovens', 'meeting_weekday' => Weekday::Wednesday]);
        $teacher = User::factory()->teacherOf($classroom)->create();
        $sunday = ClassMeeting::factory()->for($classroom)->on('2026-10-04')->cancelled()->create();

        $this->actingAs($teacher)->post("/admin/encontros/{$sunday->id}/restaurar")
            ->assertSessionHasErrors(['meeting' => 'Esta classe se reúne às quartas-feiras.']);

        $this->assertTrue($sunday->refresh()->isCancelled());
    }
}
