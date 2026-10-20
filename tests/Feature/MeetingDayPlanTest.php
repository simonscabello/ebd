<?php

namespace Tests\Feature;

use App\Actions\Classrooms\ChangeMeetingWeekday;
use App\Actions\Engagement\AwardBadges;
use App\Actions\Library\PublishSeriesVersion;
use App\Actions\Meetings\FillSundays;
use App\Actions\Meetings\RecordAttendance;
use App\Enums\Badge;
use App\Enums\Weekday;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\LessonReading;
use App\Models\Series;
use App\Models\User;
use App\Support\OfficialLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * O plano de leitura acompanha o dia de encontro da turma: ao copiar um
 * estudo feito para o domingo para uma turma de quarta e ao trocar o dia da
 * turma, a segunda vira quinta, e assim por diante. Para quem continua no
 * domingo, nada muda.
 */
class MeetingDayPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'America/Sao_Paulo']);
        // Domingo, 27/09/2026, 10h.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 9, 27)->setTime(10, 0));
    }

    /** Leituras de segunda a sábado (Gn 1 … Gn 6) e um bloco na quarta. */
    private function sundayPlan(Lesson $lesson): void
    {
        foreach (range(1, 6) as $day) {
            LessonReading::factory()->for($lesson)->create(['weekday' => $day, 'reference' => "Gn {$day}", 'position' => $day]);
        }

        LessonReading::factory()->for($lesson)->create(['weekday' => null, 'reference' => 'Sl 1', 'position' => 7]);
        LessonBlock::factory()->for($lesson)->create(['kind' => 'curiosity', 'audience' => 'student', 'drip_weekday' => 3, 'title' => 'Da quarta']);
        LessonBlock::factory()->for($lesson)->create(['kind' => 'curiosity', 'audience' => 'student', 'drip_weekday' => null, 'title' => 'Sem dia']);
    }

    /**
     * @return array<string, int|null>
     */
    private function readingDays(Lesson $lesson): array
    {
        return $lesson->readings()->orderBy('position')->get()
            ->mapWithKeys(fn (LessonReading $r) => [$r->reference => $r->weekday?->value])->all();
    }

    public function test_shifting_a_plan_day_between_meeting_days(): void
    {
        $this->assertSame(Weekday::Thursday, Weekday::Monday->shiftedBetween(Weekday::Sunday, Weekday::Wednesday));
        $this->assertSame(Weekday::Tuesday, Weekday::Saturday->shiftedBetween(Weekday::Sunday, Weekday::Wednesday));
        $this->assertSame(Weekday::Wednesday, Weekday::Sunday->shiftedBetween(Weekday::Sunday, Weekday::Wednesday));
        $this->assertSame(Weekday::Sunday, Weekday::Saturday->shiftedBetween(Weekday::Saturday, Weekday::Sunday));
        $this->assertSame(Weekday::Monday, Weekday::Thursday->shiftedBetween(Weekday::Wednesday, Weekday::Sunday));

        // Domingo para domingo: nada muda.
        foreach (Weekday::cases() as $day) {
            $this->assertSame($day, $day->shiftedBetween(Weekday::Sunday, Weekday::Sunday));
        }
    }

    public function test_copying_a_sunday_study_to_a_wednesday_class_moves_the_plan(): void
    {
        $owner = User::factory()->admin()->create();
        $library = OfficialLibrary::classroom();
        $official = Series::factory()->for($library)->create();
        $lesson = Lesson::factory()->for($library)->forSeries($official)->number(1)->published()->create();
        $this->sundayPlan($lesson);
        $version = app(PublishSeriesVersion::class)->handle($owner, $official, null);

        $wednesday = Classroom::factory()->create(['meeting_weekday' => Weekday::Wednesday]);
        $sunday = Classroom::factory()->create();

        foreach ([$wednesday, $sunday] as $classroom) {
            $this->actingAs(User::factory()->teacherOf($classroom)->create())
                ->post(route('admin.library.copy', $official), ['classroom_id' => $classroom->id])
                ->assertSessionHasNoErrors();
        }

        $copyOn = fn (Classroom $classroom) => Lesson::query()->whereBelongsTo($classroom)->sole();

        $this->assertSame(['Gn 1' => 4, 'Gn 2' => 5, 'Gn 3' => 6, 'Gn 4' => 7, 'Gn 5' => 1, 'Gn 6' => 2, 'Sl 1' => null], $this->readingDays($copyOn($wednesday)));
        $this->assertSame([6, null], $copyOn($wednesday)->blocks()->orderBy('id')->get()->map(fn ($b) => $b->drip_weekday?->value)->all());

        // Turma de domingo: igual ao original.
        $this->assertSame(['Gn 1' => 1, 'Gn 2' => 2, 'Gn 3' => 3, 'Gn 4' => 4, 'Gn 5' => 5, 'Gn 6' => 6, 'Sl 1' => null], $this->readingDays($copyOn($sunday)));
        $this->assertSame([3, null], $copyOn($sunday)->blocks()->orderBy('id')->get()->map(fn ($b) => $b->drip_weekday?->value)->all());
        $this->assertNotNull($version);
    }

    public function test_copied_plan_shows_in_order_on_the_wednesday_week(): void
    {
        $owner = User::factory()->admin()->create();
        $library = OfficialLibrary::classroom();
        $official = Series::factory()->for($library)->create();
        $lesson = Lesson::factory()->for($library)->forSeries($official)->number(1)->published()->create();
        $this->sundayPlan($lesson);
        app(PublishSeriesVersion::class)->handle($owner, $official, null);

        $classroom = Classroom::factory()->create(['meeting_weekday' => Weekday::Wednesday]);
        $teacher = User::factory()->teacherOf($classroom)->create();
        $student = User::factory()->studentOf($classroom)->create();
        $this->actingAs($teacher)->post(route('admin.library.copy', $official), ['classroom_id' => $classroom->id]);

        $copy = Lesson::query()->whereBelongsTo($classroom)->sole();
        $copy->forceFill(['status' => 'published', 'published_at' => now()])->save();
        ClassMeeting::factory()->for($classroom)->on('2026-09-30')->create(['lesson_id' => $copy->id]);

        $this->actingAs($student)->get('/minha-semana')
            ->assertInertia(fn (Assert $page) => $page
                ->where('week.days.0.weekday', 4)
                ->where('week.days.0.readings.0.reference', 'Gn 1')
                ->where('week.days.3.weekday', 7)
                ->where('week.days.3.readings.0.reference', 'Gn 4')
                ->where('week.days.5.weekday', 2)
                ->where('week.days.5.readings.0.reference', 'Gn 6')
                ->where('week.days.6.is_meeting_day', true)
                ->where('week.days.6.readings', [])
                ->where('week.progress.days_total', 6));
    }

    public function test_changing_the_day_moves_the_plan_of_future_lessons_only(): void
    {
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->teacherOf($classroom)->create();
        $student = User::factory()->studentOf($classroom)->create();

        $past = Lesson::factory()->for($classroom)->published()->on('2026-09-20')->create();
        app(RecordAttendance::class)->handle(ClassMeeting::query()->where('lesson_id', $past->id)->sole(), [$student->id], 0, $teacher);
        $next = Lesson::factory()->for($classroom)->published()->on('2026-10-04')->create();
        $draft = Lesson::factory()->for($classroom)->create();

        foreach ([$past, $next, $draft] as $lesson) {
            $this->sundayPlan($lesson);
        }

        // Já leu segunda e terça da lição seguinte (adiantou no mesmo dia).
        foreach ([1, 2] as $day) {
            DB::table('reading_checkins')->insert(['user_id' => $student->id, 'lesson_id' => $next->id, 'weekday' => $day, 'read_on' => '2026-09-27', 'created_at' => now()]);
        }

        app(ChangeMeetingWeekday::class)->handle($classroom, Weekday::Wednesday);

        $shifted = ['Gn 1' => 4, 'Gn 2' => 5, 'Gn 3' => 6, 'Gn 4' => 7, 'Gn 5' => 1, 'Gn 6' => 2, 'Sl 1' => null];
        $this->assertSame($shifted, $this->readingDays($next));
        $this->assertSame($shifted, $this->readingDays($draft));
        $this->assertSame([6, null], $next->blocks()->orderBy('id')->get()->map(fn ($b) => $b->drip_weekday?->value)->all());
        // A lição já dada fica como foi.
        $this->assertSame(['Gn 1' => 1, 'Gn 2' => 2, 'Gn 3' => 3, 'Gn 4' => 4, 'Gn 5' => 5, 'Gn 6' => 6, 'Sl 1' => null], $this->readingDays($past));
        // As marcações acompanham o dia do plano.
        $this->assertSame([4, 5], DB::table('reading_checkins')->where('lesson_id', $next->id)->orderBy('weekday')->pluck('weekday')->map(fn ($d) => (int) $d)->all());
    }

    public function test_a_lesson_left_on_the_old_day_keeps_its_plan(): void
    {
        $classroom = Classroom::factory()->create();
        $lesson = Lesson::factory()->for($classroom)->published()->on('2026-10-04')->create();
        $this->sundayPlan($lesson);
        // A quarta da semana já está ocupada por outro encontro.
        ClassMeeting::factory()->for($classroom)->on('2026-09-30')->create(['title' => 'Encontro extra']);

        app(ChangeMeetingWeekday::class)->handle($classroom, Weekday::Wednesday);

        $this->assertSame('2026-10-04', ClassMeeting::query()->where('lesson_id', $lesson->id)->sole()->held_on->toDateString());
        $this->assertSame(['Gn 1' => 1, 'Gn 2' => 2, 'Gn 3' => 3, 'Gn 4' => 4, 'Gn 5' => 5, 'Gn 6' => 6, 'Sl 1' => null], $this->readingDays($lesson));
    }

    public function test_a_sunday_class_that_keeps_its_day_does_not_change(): void
    {
        $classroom = Classroom::factory()->create();
        $lesson = Lesson::factory()->for($classroom)->published()->on('2026-10-04')->create();
        $this->sundayPlan($lesson);

        $this->actingAs(User::factory()->admin()->create())
            ->put("/admin/classes/{$classroom->slug}", ['name' => 'Outro nome', 'is_active' => true, 'meeting_weekday' => 7])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-10-04', ClassMeeting::query()->where('lesson_id', $lesson->id)->sole()->held_on->toDateString());
        $this->assertSame(['Gn 1' => 1, 'Gn 2' => 2, 'Gn 3' => 3, 'Gn 4' => 4, 'Gn 5' => 5, 'Gn 6' => 6, 'Sl 1' => null], $this->readingDays($lesson));
    }

    public function test_a_cancelled_meeting_stays_and_its_week_gets_no_other_meeting(): void
    {
        // Quinta, 01/10: a quarta desta semana já passou, então tudo anda para a frente.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 10, 1)->setTime(10, 0));
        $classroom = Classroom::factory()->create();
        $a = Lesson::factory()->for($classroom)->published()->on('2026-10-04')->create();
        $cancelled = ClassMeeting::factory()->for($classroom)->on('2026-10-11')->cancelled()->create();
        $b = Lesson::factory()->for($classroom)->published()->on('2026-10-18')->create();

        app(ChangeMeetingWeekday::class)->handle($classroom, Weekday::Wednesday);
        app(FillSundays::class)->handle($classroom->refresh());

        $this->assertSame('2026-10-11', $cancelled->refresh()->held_on->toDateString());
        $this->assertSame('2026-10-07', $a->refresh()->scheduled_for->toDateString());
        $this->assertSame('2026-10-21', $b->refresh()->scheduled_for->toDateString());
        // A semana do "sem EBD" (08 a 14/10) não ganha um encontro na quarta.
        $this->assertFalse(ClassMeeting::query()->whereBelongsTo($classroom)->whereDate('held_on', '2026-10-14')->exists());
    }

    public function test_full_week_badge_uses_the_day_of_each_meeting(): void
    {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->studentOf($classroom)->create();
        $old = Lesson::factory()->for($classroom)->published()->on('2026-09-20')->create();

        // Segunda a sábado antes do domingo 20/09.
        foreach (range(1, 6) as $day) {
            DB::table('reading_checkins')->insert(['user_id' => $student->id, 'lesson_id' => $old->id, 'weekday' => $day, 'read_on' => '2026-09-1'.($day + 3), 'created_at' => now()]);
        }

        $classroom->forceFill(['meeting_weekday' => Weekday::Wednesday])->save();

        // A quarta lida antes do domingo 20/09 continua contando.
        $this->assertSame(6, app(AwardBadges::class)->progress($student, null)[Badge::FirstFullWeek->value]['current']);
    }
}
