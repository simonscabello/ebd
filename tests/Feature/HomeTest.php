<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

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

    public function test_student_sees_the_next_published_lesson_of_their_classroom(): void
    {
        $classroom = Classroom::factory()->create(['name' => 'Jovens', 'slug' => 'jovens']);
        $series = Series::factory()->for($classroom)->create(['title' => 'Jornada dos Milagres de Jesus']);
        $student = User::factory()->studentOf($classroom)->create(['name' => 'João Pereira']);

        Lesson::factory()->forSeries($series)->completed()->on('2026-09-20')->create(['title' => 'Aula passada']);
        Lesson::factory()->forSeries($series)->on('2026-09-27')->create(['title' => 'Rascunho no mesmo dia']);
        $next = Lesson::factory()->forSeries($series)->published()->on('2026-09-27')->create(['title' => 'A Santidade de Deus']);
        Lesson::factory()->forSeries($series)->published()->on('2026-10-04')->create(['title' => 'Mais adiante']);
        LessonReading::factory()->for($next)->create(['weekday' => 3, 'reference' => 'Salmo 99']);

        $this->actingAs($student)->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('home')
                ->where('greeting', 'Bom dia')
                ->where('nextLesson.title', 'A Santidade de Deus')
                ->where('nextLesson.days_until', 4)
                ->where('nextLesson.readings.0.is_today', true)
                ->where('currentSeries.title', 'Jornada dos Milagres de Jesus')
                ->where('isMember', true)
                ->has('recentLessons', 1));
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
            ->where('nextLesson', null));
    }
}
