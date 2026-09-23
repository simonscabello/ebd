<?php

namespace Tests\Feature;

use App\Enums\Badge;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\LessonQuestion;
use App\Models\LessonReading;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Estudo do aluno durante a semana: "Li hoje", Minha semana, revisão,
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

    private function checkins(): array
    {
        return DB::table('reading_checkins')->where('user_id', $this->student->id)->orderBy('read_on')->pluck('read_on')
            ->map(fn ($d) => substr((string) $d, 0, 10))->all();
    }

    public function test_student_checks_in_today_or_yesterday_only_and_idempotently(): void
    {
        $reading = LessonReading::factory()->for($this->lesson)->create(['weekday' => 3, 'reference' => 'Rm 12.1-2']);

        $this->actingAs($this->student)->post('/licoes/e-necessario/leituras', ['reading_id' => $reading->id])->assertRedirect();
        $this->actingAs($this->student)->post('/licoes/e-necessario/leituras', ['reading_id' => $reading->id]);
        $this->actingAs($this->student)->post('/licoes/e-necessario/leituras', ['yesterday' => true]);

        $this->assertSame(['2026-09-22', '2026-09-23'], $this->checkins());

        // Desfazer só vale para hoje/ontem.
        $this->actingAs($this->student)->delete('/licoes/e-necessario/leituras', ['date' => '2026-09-20'])->assertNotFound();
        $this->actingAs($this->student)->delete('/licoes/e-necessario/leituras', ['date' => '2026-09-23']);
        $this->assertSame(['2026-09-22'], $this->checkins());
    }

    public function test_only_members_can_check_in_and_guests_are_sent_to_login(): void
    {
        $this->post('/licoes/e-necessario/leituras')->assertRedirect(route('login'));

        $outsider = User::factory()->studentOf(Classroom::factory()->create())->create();
        $this->actingAs($outsider)->post('/licoes/e-necessario/leituras')->assertForbidden();

        $foreignReading = LessonReading::factory()->create();
        $this->actingAs($this->student)
            ->post('/licoes/e-necessario/leituras', ['reading_id' => $foreignReading->id])
            ->assertSessionHasErrors('reading_id');
    }

    public function test_my_week_shows_days_readings_drip_blocks_and_checklist(): void
    {
        LessonReading::factory()->for($this->lesson)->create(['weekday' => 1, 'reference' => 'Jo 1.1-14']);
        LessonReading::factory()->for($this->lesson)->create(['weekday' => 3, 'reference' => 'Rm 12.1-2']);
        LessonBlock::factory()->for($this->lesson)->drip(3)->create(['title' => 'Siloé significa Enviado']);
        LessonBlock::factory()->for($this->lesson)->drip(5)->create(['title' => 'Sexta']);
        LessonBlock::factory()->for($this->lesson)->teacherOnly()->create(['title' => 'Roteiro secreto']);
        LessonQuestion::factory()->for($this->lesson)->review()->create();
        DB::table('reading_checkins')->insert(['user_id' => $this->student->id, 'lesson_id' => $this->lesson->id, 'read_on' => '2026-09-21', 'created_at' => now()]);

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
                ->where('week.review.total', 1)
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

    public function test_review_attempts_are_saved_only_for_review_questions(): void
    {
        $review = LessonQuestion::factory()->for($this->lesson)->review()->create();
        $reflection = LessonQuestion::factory()->for($this->lesson)->create();

        $this->actingAs($this->student)
            ->post("/licoes/e-necessario/perguntas/{$review->id}/tentativa", ['self_assessment' => 'partial'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->student)
            ->post("/licoes/e-necessario/perguntas/{$review->id}/tentativa", ['self_assessment' => 'correct']);
        $this->actingAs($this->student)
            ->post("/licoes/e-necessario/perguntas/{$reflection->id}/tentativa", ['self_assessment' => 'correct'])
            ->assertSessionHasErrors('self_assessment');

        $this->assertDatabaseCount('question_attempts', 1);
        $this->assertDatabaseHas('question_attempts', ['lesson_question_id' => $review->id, 'self_assessment' => 'correct']);

        // Pergunta de outra lição não passa pela rota desta lição.
        $foreign = LessonQuestion::factory()->review()->create();
        $this->actingAs($this->student)
            ->post("/licoes/e-necessario/perguntas/{$foreign->id}/tentativa", ['self_assessment' => 'correct'])
            ->assertNotFound();
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
        foreach (['2026-09-14', '2026-09-15', '2026-09-16', '2026-09-17', '2026-09-18', '2026-09-19', '2026-09-20', '2026-09-21', '2026-09-22'] as $date) {
            DB::table('reading_checkins')->insert(['user_id' => $this->student->id, 'lesson_id' => $this->lesson->id, 'read_on' => $date, 'created_at' => now()]);
        }

        $this->actingAs($this->student)->post('/licoes/e-necessario/leituras')->assertRedirect();
        $this->assertSame([Badge::Streak7->label(), Badge::FirstFullWeek->label()], collect(session('inertia.flash_data.badges'))->pluck('label')->all());

        $this->actingAs($this->student)->post('/licoes/e-necessario/leituras');
        $this->assertSame(2, DB::table('user_badges')->where('user_id', $this->student->id)->count());

        $this->actingAs($this->student)->get('/meu-progresso')
            ->assertInertia(fn (Assert $page) => $page
                ->component('my-progress')
                ->where('progress.streak.current', 10)
                ->has('progress.badges', 2));
    }

    public function test_review_master_badge_for_answering_every_review_question_of_the_series(): void
    {
        $series = Series::factory()->for($this->classroom)->create();
        $this->lesson->forceFill(['series_id' => $series->id])->save();
        $questions = LessonQuestion::factory()->for($this->lesson)->review()->count(5)->create();

        foreach ($questions as $question) {
            $this->actingAs($this->student)->post("/licoes/e-necessario/perguntas/{$question->id}/tentativa", ['self_assessment' => 'wrong']);
        }

        $this->assertDatabaseHas('user_badges', ['user_id' => $this->student->id, 'badge' => 'review_master', 'series_id' => $series->id]);
    }

    public function test_meu_progresso_lists_studied_lessons(): void
    {
        $past = Lesson::factory()->for($this->classroom)->published()->on('2026-09-20')->create(['title' => 'Passada']);
        LessonReading::factory()->for($past)->count(3)->sequence(['weekday' => 1], ['weekday' => 2], ['weekday' => 3])->create();
        DB::table('reading_checkins')->insert(['user_id' => $this->student->id, 'lesson_id' => $past->id, 'read_on' => '2026-09-15', 'created_at' => now()]);
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
