<?php

namespace Tests\Feature\Teaching;

use App\Actions\Meetings\SyncUpcomingMeeting;
use App\Actions\Teaching\AdvanceLesson;
use App\Actions\Teaching\ConcludeSeries;
use App\Actions\Teaching\SetCurrentLesson;
use App\Actions\Teaching\SetCurrentSeries;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Queries\CurrentLessonQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A lição atual da classe: escolher, concluir e passar, série atual e série
 * concluída. Os encontros seguem a lição atual (SyncUpcomingMeeting).
 */
class CurrentLessonTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private Series $series;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'America/Sao_Paulo']);
        // Quarta-feira, 28/10/2026. A classe se reúne no domingo (01/11).
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 10, 28)->setTime(10, 0));

        $this->classroom = Classroom::factory()->create();
        $this->series = Series::factory()->for($this->classroom)->create();
    }

    private function lesson(int $number, ?Series $series = null): Lesson
    {
        return Lesson::factory()->forSeries($series ?? $this->series)->number($number)->create(['title' => "L{$number}"]);
    }

    /**
     * @return array<string, string|null> data => título da lição (ou estado)
     */
    private function agenda(): array
    {
        return ClassMeeting::query()
            ->whereBelongsTo($this->classroom)
            ->chronological()
            ->with('lesson')
            ->get()
            ->mapWithKeys(fn (ClassMeeting $m) => [
                $m->held_on->toDateString() => $m->isCancelled() ? 'SEM EBD' : ($m->lesson->title ?? $m->title),
            ])
            ->all();
    }

    public function test_choosing_a_lesson_makes_it_current_and_puts_it_on_the_next_meeting_only(): void
    {
        [$l1, $l2, $l3] = [$this->lesson(1), $this->lesson(2), $this->lesson(3)];
        // Agenda antiga, planejada à frente: a sequência agora vem da série.
        ClassMeeting::factory()->forLesson($l3)->on('2026-11-01')->create();
        ClassMeeting::factory()->forLesson($l2)->on('2026-11-08')->create();

        app(SetCurrentLesson::class)->handle($this->classroom, $l1);

        $this->classroom->refresh();
        $this->assertSame($l1->id, $this->classroom->current_lesson_id);
        $this->assertSame($this->series->id, $this->classroom->current_series_id);
        $this->assertNotNull($this->classroom->current_lesson_started_at);
        $this->assertSame(['2026-11-01' => 'L1', '2026-11-08' => null], $this->agenda());
        $this->assertSame('2026-11-01', $l1->refresh()->scheduled_for?->toDateString());
        $this->assertNull($l3->refresh()->scheduled_for);
    }

    public function test_a_classroom_without_meetings_gets_the_next_meeting_day(): void
    {
        app(SetCurrentLesson::class)->handle($this->classroom, $this->lesson(1));

        $this->assertSame(['2026-11-01' => 'L1'], $this->agenda());
    }

    public function test_lesson_or_series_of_another_classroom_is_refused(): void
    {
        $other = Series::factory()->create();
        $foreign = Lesson::factory()->forSeries($other)->create();

        try {
            app(SetCurrentLesson::class)->handle($this->classroom, $foreign);
            $this->fail('Aceitou lição de outra classe.');
        } catch (ValidationException) {
        }

        $this->expectException(ValidationException::class);
        app(SetCurrentSeries::class)->handle($this->classroom, $other);
    }

    public function test_advancing_marks_studied_and_moves_to_the_next_unstudied_lesson(): void
    {
        [$l1, $l2, $l3] = [$this->lesson(1), $this->lesson(2), $this->lesson(3)];
        $l2->forceFill(['studied_at' => now()->subMonth()])->save();
        app(SetCurrentLesson::class)->handle($this->classroom, $l1);

        $next = app(AdvanceLesson::class)->handle($this->classroom, $l1->id);

        $this->assertSame($l3->id, $next?->id);
        $this->assertNotNull($l1->refresh()->studied_at);
        $this->assertSame(['2026-11-01' => 'L3'], $this->agenda());

        // Clique duplo (ou aba antiga) com a lição de antes: nada muda.
        app(AdvanceLesson::class)->handle($this->classroom, $l1->id);
        $this->assertSame($l3->id, $this->classroom->refresh()->current_lesson_id);
        $this->assertNull($l3->refresh()->studied_at);
    }

    public function test_advancing_goes_back_to_lessons_left_behind_and_ends_without_a_lesson(): void
    {
        [$l1, $l2, $l3] = [$this->lesson(1), $this->lesson(2), $this->lesson(3)];
        app(SetCurrentLesson::class)->handle($this->classroom, $l3);

        $this->assertSame($l1->id, app(AdvanceLesson::class)->handle($this->classroom, $l3->id)?->id);
        $this->assertSame($l2->id, app(AdvanceLesson::class)->handle($this->classroom, $l1->id)?->id);
        $this->assertNull(app(AdvanceLesson::class)->handle($this->classroom, $l2->id));

        $this->classroom->refresh();
        $this->assertNull($this->classroom->current_lesson_id);
        $this->assertNull($this->classroom->current_lesson_started_at);
        $this->assertSame($this->series->id, $this->classroom->current_series_id);
        // O domingo continua na agenda, sem lição.
        $this->assertSame(['2026-11-01' => null], $this->agenda());
    }

    public function test_on_the_meeting_day_the_taken_attendance_stays_and_the_next_week_gets_the_new_lesson(): void
    {
        [$l1, $l2] = [$this->lesson(1), $this->lesson(2)];
        app(SetCurrentLesson::class)->handle($this->classroom, $l1);

        // Domingo, depois da chamada.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 11, 1)->setTime(11, 0));
        ClassMeeting::query()->whereBelongsTo($this->classroom)->update([
            'status' => MeetingStatus::Held, 'attendance_taken_at' => now(),
        ]);

        app(AdvanceLesson::class)->handle($this->classroom, $l1->id);

        $this->assertSame(['2026-11-01' => 'L1', '2026-11-08' => 'L2'], $this->agenda());
    }

    public function test_on_the_meeting_day_before_attendance_the_meeting_takes_the_new_lesson(): void
    {
        [$l1, $l2] = [$this->lesson(1), $this->lesson(2)];
        app(SetCurrentLesson::class)->handle($this->classroom, $l1);
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 11, 1)->setTime(8, 0));

        app(AdvanceLesson::class)->handle($this->classroom, $l1->id);

        $this->assertSame(['2026-11-01' => 'L2'], $this->agenda());
    }

    public function test_a_lesson_not_concluded_stays_for_the_next_meeting(): void
    {
        $l1 = $this->lesson(1);
        $this->lesson(2);
        app(SetCurrentLesson::class)->handle($this->classroom, $l1);

        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 11, 1)->setTime(11, 0));
        ClassMeeting::query()->whereBelongsTo($this->classroom)->update([
            'status' => MeetingStatus::Held, 'attendance_taken_at' => now(),
        ]);
        // Sem concluir, a projeção leva a mesma lição para o domingo seguinte.
        app(SyncUpcomingMeeting::class)->handle($this->classroom);

        $this->assertSame(['2026-11-01' => 'L1', '2026-11-08' => 'L1'], $this->agenda());
    }

    public function test_cancelled_sundays_and_events_are_left_alone(): void
    {
        ClassMeeting::factory()->for($this->classroom)->on('2026-11-01')->cancelled()->create(['title' => 'Retiro']);
        ClassMeeting::factory()->for($this->classroom)->on('2026-11-08')->create(['title' => 'Culto da família']);

        app(SetCurrentLesson::class)->handle($this->classroom, $this->lesson(1));

        $this->assertSame([
            '2026-11-01' => 'SEM EBD',
            '2026-11-08' => 'Culto da família',
            '2026-11-15' => 'L1',
        ], $this->agenda());
    }

    public function test_current_series_picks_its_first_unstudied_lesson_and_conclusion_clears_it(): void
    {
        $this->lesson(1);
        $other = Series::factory()->for($this->classroom)->create();
        $b1 = $this->lesson(1, $other);
        $b2 = $this->lesson(2, $other);

        $this->assertSame($b1->id, app(SetCurrentSeries::class)->handle($this->classroom, $other)?->id);
        $this->assertSame(['2026-11-01' => 'L1'], $this->agenda());

        app(ConcludeSeries::class)->handle($other);

        $this->classroom->refresh();
        $this->assertNotNull($other->refresh()->concluded_at);
        $this->assertNotNull($b1->refresh()->studied_at);
        $this->assertNull($this->classroom->current_series_id);
        $this->assertNull($this->classroom->current_lesson_id);
        $this->assertSame(['2026-11-01' => null], $this->agenda());

        // Voltar à série reabre e segue da lição que faltou.
        $this->assertSame($b2->id, app(SetCurrentSeries::class)->handle($this->classroom, $other)?->id);
        $this->assertNull($other->refresh()->concluded_at);

        app(ConcludeSeries::class)->reopen($other);
        $this->assertNull($other->refresh()->concluded_at);
    }

    public function test_concluding_another_series_does_not_touch_the_current_one(): void
    {
        $l1 = $this->lesson(1);
        $old = Series::factory()->for($this->classroom)->create();
        app(SetCurrentLesson::class)->handle($this->classroom, $l1);

        app(ConcludeSeries::class)->handle($old);

        $this->assertSame($l1->id, $this->classroom->refresh()->current_lesson_id);
        $this->assertSame($this->series->id, $this->classroom->current_series_id);
    }

    public function test_a_standalone_lesson_keeps_the_current_series_and_hands_back_to_it(): void
    {
        $l1 = $this->lesson(1);
        app(SetCurrentSeries::class)->handle($this->classroom, $this->series);
        $extra = Lesson::factory()->create(['classroom_id' => $this->classroom->id, 'title' => 'Avulsa']);

        app(SetCurrentLesson::class)->handle($this->classroom, $extra);

        $this->assertSame($this->series->id, $this->classroom->refresh()->current_series_id);
        $this->assertSame(['2026-11-01' => 'Avulsa'], $this->agenda());

        $this->assertSame($l1->id, app(AdvanceLesson::class)->handle($this->classroom, $extra->id)?->id);
        $this->assertNotNull($extra->refresh()->studied_at);
    }

    public function test_a_meeting_the_teacher_named_keeps_its_lesson(): void
    {
        $l1 = $this->lesson(1);
        $l3 = $this->lesson(3);
        ClassMeeting::factory()->forLesson($l3)->on('2026-11-01')->create(['title' => 'Aula especial']);

        app(SetCurrentLesson::class)->handle($this->classroom, $l1);

        $this->assertSame(['2026-11-01' => 'L3', '2026-11-08' => 'L1'], $this->agenda());

        // Para o aluno, a aula especial vem antes: é ela que acontece no domingo.
        $week = app(CurrentLessonQuery::class)->for($this->classroom->refresh(), null);
        $this->assertSame('2026-11-01', $week->meeting?->held_on->toDateString());
        $this->assertSame($l3->id, $week->meeting->lesson_id);
    }

    public function test_the_next_free_day_is_found_even_after_a_long_break(): void
    {
        $day = now('America/Sao_Paulo')->setDate(2026, 11, 1);
        for ($i = 0; $i < 60; $i++, $day = $day->addWeek()) {
            ClassMeeting::factory()->for($this->classroom)->on($day->toDateString())->cancelled()->create();
        }

        app(SetCurrentLesson::class)->handle($this->classroom, $this->lesson(1));

        $this->assertSame('L1', $this->agenda()[$day->toDateString()]);
    }

    public function test_choosing_the_current_series_again_leaves_a_standalone_lesson_or_picks_a_new_first_lesson(): void
    {
        // Série ativada ainda vazia; a primeira lição chega depois.
        app(SetCurrentSeries::class)->handle($this->classroom, $this->series);
        $l1 = $this->lesson(1);
        $this->assertSame($l1->id, app(SetCurrentSeries::class)->handle($this->classroom, $this->series)?->id);

        $extra = Lesson::factory()->create(['classroom_id' => $this->classroom->id]);
        app(SetCurrentLesson::class)->handle($this->classroom, $extra);
        $this->assertSame($l1->id, app(SetCurrentSeries::class)->handle($this->classroom, $this->series)?->id);
        $this->assertSame($l1->id, $this->classroom->refresh()->current_lesson_id);
    }

    public function test_a_series_concluded_in_another_tab_is_reopened_when_chosen(): void
    {
        $this->lesson(1);
        $stale = Series::query()->findOrFail($this->series->id);
        app(ConcludeSeries::class)->handle($this->series);

        app(SetCurrentSeries::class)->handle($this->classroom, $stale);

        $this->assertNull($this->series->refresh()->concluded_at);
        $this->assertSame($this->series->id, $this->classroom->refresh()->current_series_id);
    }
}
