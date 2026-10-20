<?php

namespace App\Actions\Meetings;

use App\Actions\Lessons\SyncLessonSchedule;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Support\ChurchCalendar;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * "Planejar trimestre": cria um encontro por domingo no intervalo e, se uma
 * série for informada, distribui nos encontros sem lição as lições da série
 * que ainda não têm data (na ordem do número da revista).
 *
 * Como no FillSundays, uma semana que já tem encontro (mesmo em outro dia,
 * de antes de a turma trocar de dia) não ganha outro. A biblioteca oficial
 * não tem agenda.
 */
class PlanMeetings
{
    public function __construct(
        private readonly SyncLessonSchedule $syncSchedule,
    ) {}

    /**
     * @return array{created: int, assigned: int}
     */
    public function handle(Classroom $classroom, CarbonInterface $from, CarbonInterface $to, ?Series $series): array
    {
        $classroom->ensureHoldsMeetings('from');

        return DB::transaction(function () use ($classroom, $from, $to, $series) {
            $first = ChurchCalendar::onOrAfter($from, $classroom->meeting_weekday);

            $existing = ClassMeeting::query()
                ->whereBelongsTo($classroom)
                ->whereBetween('held_on', [$first->subDays(6)->toDateString(), $to->toDateString()])
                ->pluck('held_on')
                ->map(fn ($d) => $d->toDateString())
                ->all();

            $created = 0;
            $sunday = $first;

            while ($sunday->toDateString() <= $to->toDateString()) {
                // A semana termina no dia do encontro: os seis dias antes dele e ele.
                $weekStart = $sunday->subDays(6)->toDateString();
                $weekHasMeeting = array_filter($existing, fn (string $d) => $d >= $weekStart && $d <= $sunday->toDateString()) !== [];

                if (! $weekHasMeeting) {
                    ClassMeeting::query()->create([
                        'classroom_id' => $classroom->id,
                        'held_on' => $sunday->toDateString(),
                    ]);
                    $existing[] = $sunday->toDateString();
                    $created++;
                }

                $sunday = $sunday->addWeek();
            }

            $assigned = $series ? $this->assignSeries($classroom, $series, $from, $to) : 0;

            $this->syncSchedule->handle($classroom);

            return ['created' => $created, 'assigned' => $assigned];
        });
    }

    private function assignSeries(Classroom $classroom, Series $series, CarbonInterface $from, CarbonInterface $to): int
    {
        $lessons = Lesson::query()
            ->whereBelongsTo($series)
            ->whereDoesntHave('meetings', fn ($q) => $q->active())
            ->orderByRaw('number IS NULL, number')
            ->orderBy('id')
            ->pluck('id');

        $slots = ClassMeeting::query()
            ->whereBelongsTo($classroom)
            ->where('status', MeetingStatus::Planned)
            ->whereNull('lesson_id')
            ->whereNull('title')
            ->whereBetween('held_on', [$from->toDateString(), $to->toDateString()])
            ->chronological()
            ->get();

        $assigned = 0;

        foreach ($slots as $index => $slot) {
            if (! isset($lessons[$index])) {
                break;
            }

            $slot->update(['lesson_id' => $lessons[$index]]);
            $assigned++;
        }

        return $assigned;
    }
}
