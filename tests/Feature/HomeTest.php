<?php

namespace Tests\Feature;

use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A home mostra a lição do próximo encontro da classe (a "lição da semana").
 */
class HomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'Europe/Madrid']);
        // Quarta-feira, 23/09/2026 às 10h em Madri.
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 23)->setTime(10, 0));
    }

    public function test_student_sees_the_lesson_of_the_next_meeting_of_their_classroom(): void
    {
        $classroom = Classroom::factory()->create(['name' => 'Jovens', 'slug' => 'jovens']);
        $series = Series::factory()->for($classroom)->create(['title' => 'Jornada dos Milagres de Jesus']);
        $student = User::factory()->studentOf($classroom)->create(['name' => 'João Pereira']);

        Lesson::factory()->forSeries($series)->completed()->on('2026-09-20')->create(['title' => 'Aula passada']);
        $next = Lesson::factory()->forSeries($series)->published()->on('2026-09-27')->create(['title' => 'A Santidade de Deus']);
        Lesson::factory()->forSeries($series)->published()->on('2026-10-04')->create(['title' => 'Mais adiante']);
        LessonReading::factory()->for($next)->create(['weekday' => 3, 'reference' => 'Salmo 99']);

        $this->actingAs($student)->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('home')
                ->where('greeting', 'Bom dia')
                ->where('nextLesson.title', 'A Santidade de Deus')
                ->where('meeting.held_on', '2026-09-27')
                ->where('meeting.days_until', 4)
                ->where('meetingIndex', 1)
                ->where('meetingTotal', 1)
                ->where('nextLesson.readings.0.is_today', true)
                ->where('currentSeries.title', 'Jornada dos Milagres de Jesus')
                ->where('isMember', true)
                ->where('isStudent', true)
                ->has('recentLessons', 1)
                ->where('recentLessons.0.title', 'Aula passada'));
    }

    public function test_guest_sees_public_lessons_and_can_switch_classroom(): void
    {
        $jovens = Classroom::factory()->create(['slug' => 'jovens', 'position' => 1]);
        $adultos = Classroom::factory()->create(['slug' => 'adultos', 'position' => 2]);
        Lesson::factory()->for($jovens)->published()->on('2026-09-27')->create(['title' => 'Jovens']);
        Lesson::factory()->for($adultos)->published()->membersOnly()->on('2026-09-27')->create(['title' => 'Restrita']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('classroom.slug', 'jovens')
            ->where('nextLesson.title', 'Jovens'));

        $this->get('/?classe=adultos')->assertInertia(fn (Assert $page) => $page
            ->where('classroom.slug', 'adultos')
            ->where('nextLesson', null)
            ->where('preparing', false));
    }

    public function test_sunday_without_ebd_is_skipped_and_announced(): void
    {
        $classroom = Classroom::factory()->create();
        ClassMeeting::factory()->for($classroom)->on('2026-09-27')->cancelled()->create(['title' => 'Culto de Missões']);
        Lesson::factory()->for($classroom)->published()->on('2026-10-04')->create(['title' => 'É Necessário']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('nextLesson.title', 'É Necessário')
            ->where('meeting.held_on', '2026-10-04')
            ->has('cancelledBefore', 1)
            ->where('cancelledBefore.0.title', 'Culto de Missões'));
    }

    public function test_lesson_spanning_two_sundays_shows_second_meeting(): void
    {
        $classroom = Classroom::factory()->create();
        $lesson = Lesson::factory()->for($classroom)->published()->on('2026-09-20')->create(['title' => 'Temor Inquestionável']);
        ClassMeeting::factory()->forLesson($lesson)->on('2026-09-27')->create();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('nextLesson.title', 'Temor Inquestionável')
            ->where('meetingIndex', 2)
            ->where('meetingTotal', 2)
            ->has('recentLessons', 0));
    }

    public function test_draft_on_next_meeting_is_announced_as_in_preparation(): void
    {
        $classroom = Classroom::factory()->create();
        Lesson::factory()->for($classroom)->on('2026-09-27')->create(['title' => 'Rascunho']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('nextLesson', null)
            ->where('preparing', true)
            ->where('meeting.held_on', '2026-09-27'));
    }

    public function test_without_future_meetings_falls_back_to_the_last_held_lesson(): void
    {
        $classroom = Classroom::factory()->create();
        Lesson::factory()->for($classroom)->completed()->on('2026-09-13')->create(['title' => 'Antiga']);
        Lesson::factory()->for($classroom)->completed()->on('2026-09-20')->create(['title' => 'Última']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('nextLesson.title', 'Última')
            ->where('meeting', null)
            ->has('recentLessons', 1)
            ->where('recentLessons.0.title', 'Antiga'));
    }
}
