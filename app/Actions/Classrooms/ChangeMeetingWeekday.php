<?php

namespace App\Actions\Classrooms;

use App\Actions\Lessons\SyncLessonSchedule;
use App\Enums\MeetingStatus;
use App\Enums\Weekday;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Support\ChurchCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Troca o dia do encontro da turma. Os encontros futuros ainda planejados e
 * sem chamada vão para o novo dia dentro da mesma semana de leitura (os seis
 * dias antes do encontro até ele), mantendo lição e ordem. Passados,
 * realizados, cancelados ou com chamada ficam como estão.
 *
 * @return int encontros remarcados
 */
class ChangeMeetingWeekday
{
    public function __construct(private readonly SyncLessonSchedule $syncSchedule) {}

    public function handle(Classroom $classroom, Weekday $weekday): int
    {
        return DB::transaction(function () use ($classroom, $weekday) {
            $classroom->forceFill(['meeting_weekday' => $weekday])->save();

            $today = ChurchCalendar::todayString();

            $meetings = ClassMeeting::query()
                ->whereBelongsTo($classroom)
                ->where('status', MeetingStatus::Planned)
                ->whereNull('attendance_taken_at')
                ->whereDate('held_on', '>', $today)
                ->whereDoesntHave('attendances')
                ->orderBy('held_on')
                ->get();

            $moved = 0;

            foreach ($meetings as $meeting) {
                $old = CarbonImmutable::parse($meeting->held_on->toDateString());
                // O novo dia dentro da janela (antigo − 6 … antigo).
                $new = $old->subDays(($old->dayOfWeekIso - $weekday->value + 7) % 7);

                if ($new->toDateString() <= $today) {
                    $new = $new->addWeek();
                }

                if ($new->toDateString() !== $old->toDateString()) {
                    $meeting->forceFill(['held_on' => $new->toDateString()])->save();
                    $moved++;
                }
            }

            $this->syncSchedule->handle($classroom);

            return $moved;
        });
    }
}
