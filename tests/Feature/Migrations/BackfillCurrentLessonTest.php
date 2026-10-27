<?php

namespace Tests\Feature\Migrations;

use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A lição atual de cada classe sai da agenda que já existia: a do próximo
 * encontro com lição (senão a do último realizado); as dadas antes ficam
 * estudadas.
 */
class BackfillCurrentLessonTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_10_29_000200_backfill_current_lesson.php';

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'America/Sao_Paulo']);
        // Quarta-feira, 28/10/2026.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 10, 28)->setTime(10, 0));
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();
    }

    private function meeting(Lesson $lesson, string $date, MeetingStatus $status = MeetingStatus::Held): void
    {
        ClassMeeting::factory()->forLesson($lesson)->on($date)->create(['status' => $status]);
    }

    public function test_the_next_meeting_lesson_becomes_current_and_the_past_ones_studied(): void
    {
        $classroom = Classroom::factory()->create();
        $series = Series::factory()->for($classroom)->create();
        $first = Lesson::factory()->forSeries($series)->number(1)->create();
        $continuing = Lesson::factory()->forSeries($series)->number(2)->create();
        $later = Lesson::factory()->forSeries($series)->number(3)->create();
        $this->meeting($first, '2026-10-18');
        $this->meeting($continuing, '2026-10-25');
        $this->meeting($continuing, '2026-11-01', MeetingStatus::Planned);
        $this->meeting($later, '2026-11-08', MeetingStatus::Planned);

        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();

        $classroom->refresh();
        $this->assertSame($continuing->id, $classroom->current_lesson_id);
        $this->assertSame($series->id, $classroom->current_series_id);
        $this->assertSame('2026-10-25', $classroom->current_lesson_started_at?->toDateString());
        $this->assertSame('2026-10-18', $first->refresh()->studied_at?->toDateString());
        $this->assertNull($continuing->refresh()->studied_at);
        $this->assertNull($later->refresh()->studied_at);
    }

    public function test_without_future_meetings_the_last_held_lesson_stays_current(): void
    {
        $classroom = Classroom::factory()->create();
        $old = Lesson::factory()->for($classroom)->create();
        $last = Lesson::factory()->for($classroom)->create();
        $this->meeting($old, '2026-10-11');
        $this->meeting($last, '2026-10-18');

        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();

        $this->assertSame($last->id, $classroom->refresh()->current_lesson_id);
        $this->assertNotNull($old->refresh()->studied_at);
        $this->assertNull($last->refresh()->studied_at);
    }

    public function test_a_classroom_already_set_is_left_alone(): void
    {
        $classroom = Classroom::factory()->create();
        $chosen = Lesson::factory()->for($classroom)->create();
        $classroom->forceFill(['current_lesson_id' => $chosen->id])->save();
        $this->meeting(Lesson::factory()->for($classroom)->create(), '2026-11-01', MeetingStatus::Planned);

        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();

        $this->assertSame($chosen->id, $classroom->refresh()->current_lesson_id);
    }
}
