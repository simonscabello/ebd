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
 * Troca o dia do encontro da turma. Os encontros futuros ainda sem chamada
 * (planejados ou "sem EBD") vão para o novo dia dentro da mesma semana de
 * leitura (os seis dias antes do encontro até ele), mantendo lição e ordem.
 * Ficam como estão: passados, realizados, com chamada, o desta semana cujo
 * novo dia já passou e o que cairia numa data já ocupada.
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
                ->whereIn('status', [MeetingStatus::Planned, MeetingStatus::Cancelled])
                ->whereNull('attendance_taken_at')
                ->whereDate('held_on', '>', $today)
                ->whereDoesntHave('attendances')
                ->orderBy('held_on')
                ->get();

            $taken = array_flip(ClassMeeting::query()->whereBelongsTo($classroom)
                ->pluck('held_on')->map(fn ($d) => $d->toDateString())->all());
            $moved = 0;

            foreach ($meetings as $meeting) {
                $old = $meeting->held_on->toDateString();
                // O novo dia dentro da janela (antigo − 6 … antigo).
                $new = ChurchCalendar::onOrBefore(CarbonImmutable::parse($old), $weekday)->toDateString();

                if ($new === $old || $new <= $today || isset($taken[$new])) {
                    continue;
                }

                $meeting->forceFill(['held_on' => $new])->save();
                unset($taken[$old]);
                $taken[$new] = true;
                $moved++;
            }

            $this->syncSchedule->handle($classroom);

            return $moved;
        });
    }
}
