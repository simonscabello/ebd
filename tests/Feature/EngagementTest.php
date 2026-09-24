<?php

namespace Tests\Feature;

use App\Enums\Badge;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\LessonReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Estudo do aluno durante a semana: leituras marcadas, Minha semana,
 * anotações e selos.
 */
class EngagementTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $student;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'Europe/Madrid']);
        // Quarta-feira, 23/09/2026.
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 23)->setTime(10, 0));

        $this->classroom = Classroom::factory()->create(['slug' => 'adultos']);
        $this->student = User::factory()->managed()->studentOf($this->classroom)->create();
        $this->lesson = Lesson::factory()->for($this->classroom)->published()->number(11)->on('2026-09-27')->create([
            'slug' => 'e-necessario',
            'title' => 'É Necessário',
            'bible_reference' => 'Jo 9.1-41',
        ]);
    }

    /**
     * @return array<int, string> dia do plano => data em que marcou
     */
    private function checkins(): array
    {
        return DB::table('reading_checkins')->where('user_id', $this->student->id)->orderBy('weekday')->pluck('read_on', 'weekday')
            ->map(fn ($d) => substr((string) $d, 0, 10))->all();
    }

    private function checkIn(Lesson $lesson, int $weekday, string $readOn): void
    {
        DB::table('reading_checkins')->insert([
            'user_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'weekday' => $weekday,
            'read_on' => $readOn,
            'created_at' => now(),
        ]);
    }

    public function test_student_marks_any_day_of_the_plan_at_any_time_and_idempotently(): void
    {
        $friday = LessonReading::factory()->for($this->lesson)->create(['weekday' => 5, 'reference' => 'Rm 12.1-2']);

        // Quarta-feira: adianta a leitura de sexta e põe a de segunda em dia.
        $this->actingAs($this->student)->post('/licoes/e-necessario/leituras', ['weekday' => 5, 'reading_id' => $friday->id])->assertRedirect();
        $this->actingAs($this->student)->post('/licoes/e-necessario/leituras', ['weekday' => 5, 'reading_id' => $friday->id]);
        $this->actingAs($this->student)->post('/licoes/e-necessario/leituras', ['weekday' => 1]);

        // read_on é quando marcou; o dia do plano vem da leitura.
        $this->assertSame([1 => '2026-09-23', 5 => '2026-09-23'], $this->checkins());

        // A leitura manda no dia do plano, mesmo que o dia enviado seja outro.
        $this->actingAs($this->student)->post('/licoes/e-necessario/leituras', ['weekday' => 2, 'reading_id' => $friday->id]);
        $this->assertSame([1, 5], array_keys($this->checkins()));

        $this->actingAs($this->student)->delete('/licoes/e-necessario/leituras', ['weekday' => 1])->assertRedirect();
        $this->assertSame([5 => '2026-09-23'], $this->checkins());

        $this->actingAs($this->student)->post('/licoes/e-necessario/leituras', ['weekday' => 8])->assertSessionHasErrors('weekday');
    }

    public function test_only_members_can_check_in_and_guests_are_sent_to_login(): void
    {
        $this->post('/licoes/e-necessario/leituras', ['weekday' => 3])->assertRedirect(route('login'));

        $outsider = User::factory()->studentOf(Classroom::factory()->create())->create();
        $this->actingAs($outsider)->post('/licoes/e-necessario/leituras', ['weekday' => 3])->assertForbidden();

        $foreignReading = LessonReading::factory()->create();
        $this->actingAs($this->student)
            ->post('/licoes/e-necessario/leituras', ['weekday' => 3, 'reading_id' => $foreignReading->id])
            ->assertSessionHasErrors('reading_id');
    }

    public function test_my_week_shows_days_readings_drip_blocks_and_checklist(): void
    {
        LessonReading::factory()->for($this->lesson)->create(['weekday' => 1, 'reference' => 'Jo 1.1-14']);
        LessonReading::factory()->for($this->lesson)->create(['weekday' => 3, 'reference' => 'Rm 12.1-2']);
        LessonBlock::factory()->for($this->lesson)->drip(3)->create(['title' => 'Siloé significa Enviado']);
        LessonBlock::factory()->for($this->lesson)->drip(5)->create(['title' => 'Sexta']);
        LessonBlock::factory()->for($this->lesson)->teacherOnly()->create(['title' => 'Roteiro secreto']);
        $this->checkIn($this->lesson, 1, '2026-09-21');

        $this->actingAs($this->student)->get('/minha-semana')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('my-week')
                ->where('week.lesson.display_title', 'Lição 11 — É Necessário')
                ->where('week.meeting.days_until', 4)
                ->has('week.days', 7)
                ->where('week.days.0.done', true)
                ->where('week.days.0.readings.0.reference', 'Jo 1.1-14')
                ->where('week.days.2.is_today', true)
                ->where('week.days.2.done', false)
                ->has('week.todayBlocks', 1)
                ->where('week.todayBlocks.0.title', 'Siloé significa Enviado')
                ->has('week.unlockedBlocks', 1)
                ->where('week.progress.days_done', 1)
                ->where('week.progress.days_total', 2)
                ->missing('week.review')
                ->where('week.checklist.0.done', true)
                ->where('week.streak.current', 0));
    }

    public function test_blocks_without_day_are_spread_over_the_week(): void
    {
        foreach (['Seg', 'Ter', 'Qua'] as $title) {
            LessonBlock::factory()->for($this->lesson)->create(['title' => $title]);
        }

        $this->actingAs($this->student)->get('/minha-semana')
            ->assertInertia(fn (Assert $page) => $page
                ->where('week.todayBlocks.0.title', 'Qua')
                ->has('week.unlockedBlocks', 3));
    }

    public function test_student_in_two_classrooms_can_switch_week(): void
    {
        $jovens = Classroom::factory()->create(['slug' => 'jovens']);
        $this->student->classrooms()->attach($jovens, ['role' => 'student']);
        Lesson::factory()->for($jovens)->published()->on('2026-09-27')->create(['title' => 'Jovens']);

        $this->actingAs($this->student)->get('/minha-semana?classe=jovens')
            ->assertInertia(fn (Assert $page) => $page
                ->has('classrooms', 2)
                ->where('classroom.slug', 'jovens')
                ->where('week.lesson.title', 'Jovens'));
    }

    public function test_personal_note_is_private_to_the_student(): void
    {
        $this->actingAs($this->student)->put('/licoes/e-necessario/anotacao', ['body' => 'Uma coisa sei: eu era cego'])->assertRedirect();

        $this->actingAs($this->student)->get('/licoes/e-necessario')
            ->assertInertia(fn (Assert $page) => $page->where('study.note', 'Uma coisa sei: eu era cego'));

        $teacher = User::factory()->teacherOf($this->classroom)->create();
        $this->actingAs($teacher)->get('/licoes/e-necessario')
            ->assertInertia(fn (Assert $page) => $page->where('study.note', null));
        $this->actingAs($teacher)->get("/admin/classes/adultos/alunos/{$this->student->id}")
            ->assertOk()
            ->assertDontSee('Uma coisa sei');

        // Nota vazia apaga.
        $this->actingAs($this->student)->put('/licoes/e-necessario/anotacao', ['body' => '  ']);
        $this->assertDatabaseCount('lesson_notes', 0);
    }

    public function test_guests_get_no_personal_study_data(): void
    {
        $this->get('/licoes/e-necessario')->assertInertia(fn (Assert $page) => $page->where('study', null));
    }

    public function test_full_week_and_streak_badges_are_awarded_once(): void
    {
        // Semana passada: todas as leituras de uma lição, uma por dia.
        $previous = Lesson::factory()->for($this->classroom)->published()->on('2026-09-20')->create();
        foreach (range(1, 7) as $weekday) {
            $this->checkIn($previous, $weekday, '2026-09-'.(13 + $weekday));
        }
        $this->checkIn($this->lesson, 1, '2026-09-21');
        $this->checkIn($this->lesson, 2, '2026-09-22');

        $this->actingAs($this->student)->post('/licoes/e-necessario/leituras', ['weekday' => 3])->assertRedirect();
        $this->assertSame([Badge::Streak7->label(), Badge::FirstFullWeek->label()], collect(session('inertia.flash_data.badges'))->pluck('label')->all());

        $this->actingAs($this->student)->post('/licoes/e-necessario/leituras', ['weekday' => 4]);
        $this->assertSame(2, DB::table('user_badges')->where('user_id', $this->student->id)->count());

        $this->actingAs($this->student)->get('/meu-progresso')
            ->assertInertia(fn (Assert $page) => $page
                ->component('my-progress')
                ->where('progress.streak.current', 10)
                ->has('progress.badges', 2));
    }

    public function test_reading_everything_on_the_weekend_still_completes_the_week(): void
    {
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 26)->setTime(10, 0));

        foreach (range(1, 6) as $weekday) {
            $this->actingAs($this->student)->post('/licoes/e-necessario/leituras', ['weekday' => $weekday]);
        }

        $this->assertDatabaseHas('user_badges', ['user_id' => $this->student->id, 'badge' => Badge::FirstFullWeek->value]);
        $this->assertDatabaseMissing('user_badges', ['user_id' => $this->student->id, 'badge' => Badge::Streak7->value]);
    }

    public function test_meu_progresso_lists_studied_lessons(): void
    {
        $past = Lesson::factory()->for($this->classroom)->published()->on('2026-09-20')->create(['title' => 'Passada']);
        LessonReading::factory()->for($past)->count(3)->sequence(['weekday' => 1], ['weekday' => 2], ['weekday' => 3])->create();
        $this->checkIn($past, 1, '2026-09-15');
        $meeting = ClassMeeting::query()->where('lesson_id', $past->id)->sole();
        $meeting->forceFill(['attendance_taken_at' => now()])->save();
        DB::table('attendances')->insert(['class_meeting_id' => $meeting->id, 'user_id' => $this->student->id, 'created_at' => now()]);

        $this->actingAs($this->student)->get('/meu-progresso')
            ->assertInertia(fn (Assert $page) => $page
                ->has('progress.lessons', 1)
                ->where('progress.lessons.0.days_read', 1)
                ->where('progress.lessons.0.readings_total', 3)
                ->where('progress.lessons.0.present', 1)
                ->where('progress.lessons.0.meetings', 1));
    }
}
