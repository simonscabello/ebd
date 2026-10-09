<?php

namespace App\Actions\Meetings;

use App\Actions\Lessons\SyncLessonSchedule;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Support\ChurchCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "A conversa rendeu: a lição continua no próximo domingo."
 *
 * Marca o encontro como realizado e coloca a mesma lição no próximo encontro,
 * empurrando as seguintes. Se não houver próximo encontro, cria um no domingo
 * seguinte.
 *
 * @return int|null id da lição que ficou sem data depois do empurrão
 */
class ContinueLessonNextMeeting
{
    public function __construct(
        private readonly ShiftPlannedLessons $shift,
        private readonly SyncLessonSchedule $syncSchedule,
    ) {}

    public function handle(ClassMeeting $meeting): ?int
    {
        if ($meeting->lesson_id === null || $meeting->isCancelled()) {
            throw ValidationException::withMessages([
                'meeting' => 'Só é possível continuar um encontro que tem lição.',
            ]);
        }

        if ($meeting->held_on->toDateString() > ChurchCalendar::today()->toDateString()) {
            throw ValidationException::withMessages([
                'meeting' => 'Este encontro ainda não aconteceu.',
            ]);
        }

        return DB::transaction(function () use ($meeting) {
            $meeting->update(['status' => MeetingStatus::Held]);

            $next = ClassMeeting::query()
                ->where('classroom_id', $meeting->classroom_id)
                ->active()
                ->whereDate('held_on', '>', $meeting->held_on->toDateString())
                ->chronological()
                ->first();

            $leftover = null;

            if ($next === null) {
                ClassMeeting::query()->create([
                    'classroom_id' => $meeting->classroom_id,
                    'held_on' => $this->freeSundayAfter($meeting),
                    'lesson_id' => $meeting->lesson_id,
                ]);
            } elseif ($next->lesson_id !== $meeting->lesson_id) {
                $leftover = $this->shift->handle($meeting->classroom, $next->held_on, $meeting->lesson_id);
            }

            $this->syncSchedule->handle($meeting->classroom_id);

            return $leftover;
        });
    }

    private function freeSundayAfter(ClassMeeting $meeting): string
    {
        $date = CarbonImmutable::parse($meeting->held_on->toDateString())->next(CarbonImmutable::SUNDAY);

        $taken = ClassMeeting::query()
            ->where('classroom_id', $meeting->classroom_id)
            ->whereDate('held_on', '>', $meeting->held_on->toDateString())
            ->pluck('held_on')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        while (in_array($date->toDateString(), $taken, true)) {
            $date = $date->addWeek();
        }

        return $date->toDateString();
    }
}
