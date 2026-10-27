<?php

namespace Tests\Feature\Teaching;

use App\Actions\Teaching\SetCurrentLesson;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A lição atual pela gestão: as rotas de escolher e concluir, a aba Lições,
 * "Fazer chamada", excluir a lição atual e o comando ebd:licao-atual.
 */
class CurrentLessonPagesTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $teacher;

    private Series $series;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'America/Sao_Paulo']);
        // Quarta-feira, 28/10/2026.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 10, 28)->setTime(10, 0));

        $this->classroom = Classroom::factory()->create(['slug' => 'jovens', 'name' => 'Jovens']);
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
        $this->series = Series::factory()->for($this->classroom)->create(['title' => 'Milagres']);
    }

    private function lesson(int $number, ?Series $series = null): Lesson
    {
        return Lesson::factory()->forSeries($series ?? $this->series)->number($number)->published()->create(['title' => "L{$number}"]);
    }

    public function test_teacher_chooses_and_concludes_the_current_lesson(): void
    {
        [$l1, $l2] = [$this->lesson(1), $this->lesson(2)];

        $this->actingAs($this->teacher)->post('/admin/classes/jovens/licao-atual', ['lesson_id' => $l1->id])->assertSessionHasNoErrors();
        $this->assertSame($l1->id, $this->classroom->refresh()->current_lesson_id);

        $this->actingAs($this->teacher)->post('/admin/classes/jovens/licao-atual/concluir', ['lesson_id' => $l1->id])->assertSessionHasNoErrors();
        $this->assertSame($l2->id, $this->classroom->refresh()->current_lesson_id);
        $this->assertSame($l2->id, ClassMeeting::query()->whereDate('held_on', '2026-11-01')->value('lesson_id'));

        // Aba antiga: concluir de novo a lição de antes não pula a seguinte.
        $this->actingAs($this->teacher)->post('/admin/classes/jovens/licao-atual/concluir', ['lesson_id' => $l1->id]);
        $this->assertSame($l2->id, $this->classroom->refresh()->current_lesson_id);
    }

    public function test_series_can_become_current_and_be_concluded_and_reopened(): void
    {
        $other = Series::factory()->for($this->classroom)->create(['title' => 'Parábolas']);
        $p1 = $this->lesson(1, $other);

        $this->actingAs($this->teacher)->post('/admin/classes/jovens/serie-atual', ['series_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame([$other->id, $p1->id], [$this->classroom->refresh()->current_series_id, $this->classroom->current_lesson_id]);

        $this->actingAs($this->teacher)->post("/admin/series/{$other->id}/concluir")->assertSessionHasNoErrors();
        $this->assertNotNull($other->refresh()->concluded_at);
        $this->assertNull($this->classroom->refresh()->current_series_id);

        $this->actingAs($this->teacher)->post("/admin/series/{$other->id}/reabrir")->assertSessionHasNoErrors();
        $this->assertNull($other->refresh()->concluded_at);
    }

    public function test_lessons_and_series_of_another_classroom_are_refused(): void
    {
        $foreign = Lesson::factory()->published()->create();
        $foreignSeries = Series::factory()->create();
        $outsider = User::factory()->teacherOf(Classroom::factory()->create())->create();

        $this->actingAs($this->teacher)->post('/admin/classes/jovens/licao-atual', ['lesson_id' => $foreign->id])->assertNotFound();
        $this->actingAs($this->teacher)->post('/admin/classes/jovens/serie-atual', ['series_id' => $foreignSeries->id])->assertNotFound();
        $this->actingAs($outsider)->post('/admin/classes/jovens/licao-atual', ['lesson_id' => $this->lesson(1)->id])->assertForbidden();
        $this->actingAs($outsider)->post("/admin/series/{$this->series->id}/concluir")->assertForbidden();
        $this->actingAs($outsider)->get('/admin/classes/jovens/licoes')->assertForbidden();

        $this->assertNull($this->classroom->refresh()->current_lesson_id);
    }

    public function test_lessons_tab_lists_the_current_series_first_and_the_concluded_last(): void
    {
        $old = Series::factory()->for($this->classroom)->create(['title' => 'Gênesis', 'concluded_at' => now()->subMonth()]);
        $next = Series::factory()->for($this->classroom)->create(['title' => 'Parábolas']);
        $this->lesson(1, $old);
        $this->lesson(1, $next);
        $l1 = $this->lesson(1);
        $l2 = $this->lesson(2);
        $l1->forceFill(['studied_at' => now()])->save();
        app(SetCurrentLesson::class)->handle($this->classroom, $l2);
        Lesson::factory()->create(['classroom_id' => $this->classroom->id, 'title' => 'Avulsa']);

        $this->actingAs($this->teacher)->get('/admin/classes/jovens/licoes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/classrooms/lessons')
                ->where('series', fn ($series) => collect($series)->pluck('title')->all() === ['Milagres', 'Parábolas', 'Gênesis'])
                ->where('series.0.is_current', true)
                ->where('series.0.lessons.0.studied', true)
                ->where('series.0.lessons.1.is_current', true)
                ->where('series.2.concluded_on', now()->subMonth()->toDateString())
                ->where('standalone.0.display_title', 'Avulsa')
                ->where('header.lesson_position', ['index' => 2, 'total' => 2]));
    }

    public function test_take_attendance_opens_todays_meeting_with_the_current_lesson(): void
    {
        $l1 = $this->lesson(1);
        app(SetCurrentLesson::class)->handle($this->classroom, $l1);

        // Quarta: abre o último encontro que já aconteceu (nenhum ainda).
        $this->actingAs($this->teacher)->get('/admin/classes/jovens/chamada')->assertRedirect();
        $this->assertSame(0, ClassMeeting::query()->whereDate('held_on', '<=', '2026-10-28')->count());

        // Domingo: o encontro de hoje.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 11, 1)->setTime(9, 0));
        $meeting = ClassMeeting::query()->whereDate('held_on', '2026-11-01')->sole();

        $this->actingAs($this->teacher)->get('/admin/classes/jovens/chamada')
            ->assertRedirect("/admin/classes/jovens/domingos/{$meeting->id}");
        $this->assertSame($l1->id, $meeting->refresh()->lesson_id);
    }

    public function test_deleting_the_current_lesson_moves_the_classroom_to_the_next(): void
    {
        [$l1, $l2] = [$this->lesson(1), $this->lesson(2)];
        app(SetCurrentLesson::class)->handle($this->classroom, $l1);

        $this->actingAs($this->teacher)->delete("/admin/licoes/{$l1->id}")->assertRedirect();

        $this->assertSame($l2->id, $this->classroom->refresh()->current_lesson_id);
        $this->assertSame($l2->id, ClassMeeting::query()->whereDate('held_on', '2026-11-01')->value('lesson_id'));
    }

    public function test_moving_the_current_lesson_to_another_series_moves_the_current_series(): void
    {
        $l1 = $this->lesson(1);
        $other = Series::factory()->for($this->classroom)->create();
        app(SetCurrentLesson::class)->handle($this->classroom, $l1);

        $this->actingAs($this->teacher)->put("/admin/licoes/{$l1->id}", [
            'title' => 'L1', 'series_id' => $other->id, 'visibility' => 'public',
        ])->assertSessionHasNoErrors();

        $this->assertSame($other->id, $this->classroom->refresh()->current_series_id);
    }

    public function test_arranging_the_old_agenda_keeps_only_the_next_meeting_and_saves_a_copy(): void
    {
        Storage::fake('local');
        $l1 = $this->lesson(1);
        $l2 = $this->lesson(2);
        $this->classroom->forceFill(['current_lesson_id' => $l1->id, 'current_series_id' => $this->series->id])->save();
        ClassMeeting::factory()->forLesson($l1)->on('2026-11-01')->create();
        ClassMeeting::factory()->forLesson($l2)->on('2026-11-08')->create();
        ClassMeeting::factory()->for($this->classroom)->on('2026-11-15')->create();
        $noted = ClassMeeting::factory()->for($this->classroom)->on('2026-11-22')->create(['lesson_id' => $l2->id, 'notes' => 'Trazer mapas']);

        // Sem --force, só mostra.
        $this->artisan('ebd:licao-atual')->assertSuccessful();
        $this->assertSame(4, ClassMeeting::query()->count());

        $this->artisan('ebd:licao-atual', ['--force' => true])->assertSuccessful();

        $this->assertSame(['2026-11-01', '2026-11-22'], ClassMeeting::query()->orderBy('held_on')->pluck('held_on')->map->toDateString()->all());
        $this->assertSame($l1->id, ClassMeeting::query()->whereDate('held_on', '2026-11-01')->value('lesson_id'));
        $this->assertNull($noted->refresh()->lesson_id);
        $this->assertCount(1, Storage::disk('local')->files('backups'));

        // Rodar de novo não muda nada.
        $this->artisan('ebd:licao-atual', ['--force' => true])->assertSuccessful();
        $this->assertSame(2, ClassMeeting::query()->count());
    }
}
