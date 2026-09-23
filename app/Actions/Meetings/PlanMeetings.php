<?php

namespace App\Actions\Meetings;

use App\Actions\Lessons\SyncLessonSchedule;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * "Planejar trimestre": cria um encontro por domingo no intervalo e, se uma
 * série for informada, distribui nos encontros sem lição as lições da série
 * que ainda não têm data (na ordem do número da revista).
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
        return DB::transaction(function () use ($classroom, $from, $to, $series) {
            $existing = ClassMeeting::query()
                ->whereBelongsTo($classroom)
                ->whereBetween('held_on', [$from->toDateString(), $to->toDateString()])
                ->pluck('held_on')
                ->map(fn ($d) => $d->toDateString())
                ->all();

            $created = 0;
            $sunday = CarbonImmutable::parse($from->toDateString());
            $sunday = $sunday->isSunday() ? $sunday : $sunday->next(CarbonImmutable::SUNDAY);

            while ($sunday->lte($to)) {
                if (! in_array($sunday->toDateString(), $existing, true)) {
                    ClassMeeting::query()->create([
                        'classroom_id' => $classroom->id,
                        'held_on' => $sunday->toDateString(),
                    ]);
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
