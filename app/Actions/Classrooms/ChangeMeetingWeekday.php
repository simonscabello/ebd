<?php

namespace App\Actions\Classrooms;

use App\Actions\Lessons\SyncLessonSchedule;
use App\Enums\MeetingStatus;
use App\Enums\Weekday;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\LessonReading;
use App\Support\ChurchCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Troca o dia do encontro da turma. Os encontros futuros planejados e ainda
 * sem chamada vão para o novo dia dentro da mesma semana de leitura (os seis
 * dias antes do encontro até ele), mantendo lição e ordem.
 *
 * Quando o novo dia desta semana já passou, o encontro vai para o próximo
 * novo dia, e os seguintes também andam para a frente: a ordem e o intervalo
 * de uma semana entre as lições continuam, sem uma lição de um dia só.
 *
 * Ficam como estão: passados, realizados, com chamada, os "sem EBD" (o
 * cancelamento foi para aquela data; a semana do novo dia que contém a data
 * não ganha outro encontro em FillSundays) e o que cairia numa data já
 * ocupada.
 *
 * O plano de leitura das lições que mudaram de dia (e das que ainda não têm
 * encontro) anda junto: segunda de uma turma de domingo vira quinta numa de
 * quarta. As marcações de leitura já feitas acompanham o dia do plano.
 *
 * @return int encontros remarcados
 */
class ChangeMeetingWeekday
{
    public function __construct(private readonly SyncLessonSchedule $syncSchedule) {}

    public function handle(Classroom $classroom, Weekday $weekday): int
    {
        return DB::transaction(function () use ($classroom, $weekday) {
            $previous = $classroom->meeting_weekday;
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

            $taken = array_flip(ClassMeeting::query()->whereBelongsTo($classroom)
                ->pluck('held_on')->map(fn ($d) => $d->toDateString())->all());
            $moved = 0;
            $forward = false;
            /** @var array<int, array{0: Weekday, 1: Weekday}> $lessonShifts dia antigo e novo do 1º encontro remarcado de cada lição */
            $lessonShifts = [];
            /** @var list<int> $movedIds */
            $movedIds = [];

            foreach ($meetings as $meeting) {
                $old = CarbonImmutable::parse($meeting->held_on->toDateString());
                // O novo dia dentro da janela (antigo − 6 … antigo); se já
                // passou, o próximo novo dia, e daí em diante sempre para a frente.
                $new = ChurchCalendar::onOrBefore($old, $weekday)->toDateString();

                if ($forward || $new <= $today) {
                    $forward = true;
                    $new = ChurchCalendar::onOrAfter($old, $weekday)->toDateString();
                }

                if ($new === $old->toDateString() || isset($taken[$new])) {
                    continue;
                }

                $meeting->forceFill(['held_on' => $new])->save();
                unset($taken[$old->toDateString()]);
                $taken[$new] = true;
                $movedIds[] = $meeting->id;
                $moved++;

                if ($meeting->lesson_id !== null) {
                    $lessonShifts[$meeting->lesson_id] ??= [Weekday::from($old->dayOfWeekIso), $weekday];
                }
            }

            $this->shiftReadingPlans($classroom, $previous, $weekday, $lessonShifts, $movedIds);
            $this->syncSchedule->handle($classroom);

            return $moved;
        });
    }

    /**
     * Leva o plano de leitura (leituras, blocos e marcações) das lições que
     * só têm encontros remarcados, e das que ainda não têm encontro, para o
     * novo dia. Lição com algum encontro que ficou onde estava (passado,
     * realizado, com chamada ou numa data ocupada) mantém o plano: ele já foi
     * ou ainda será usado no dia antigo.
     *
     * @param  array<int, array{0: Weekday, 1: Weekday}>  $lessonShifts
     * @param  list<int>  $movedIds
     */
    private function shiftReadingPlans(Classroom $classroom, Weekday $previous, Weekday $weekday, array $lessonShifts, array $movedIds): void
    {
        $unscheduled = Lesson::query()
            ->whereBelongsTo($classroom)
            ->whereDoesntHave('meetings')
            ->pluck('id')
            ->all();

        foreach ($unscheduled as $lessonId) {
            $lessonShifts[$lessonId] ??= [$previous, $weekday];
        }

        $kept = ClassMeeting::query()
            ->whereIn('lesson_id', array_keys($lessonShifts))
            ->where('status', '!=', MeetingStatus::Cancelled)
            ->whereNotIn('id', $movedIds)
            ->pluck('lesson_id')
            ->all();

        foreach ($kept as $lessonId) {
            unset($lessonShifts[(int) $lessonId]);
        }

        foreach ($lessonShifts as $lessonId => [$from, $to]) {
            if ($from === $to) {
                continue;
            }

            $shift = fn (int $day) => Weekday::from($day)->shiftedBetween($from, $to)->value;

            foreach (LessonReading::query()->where('lesson_id', $lessonId)->whereNotNull('weekday')->get() as $reading) {
                $reading->forceFill(['weekday' => $shift($reading->weekday->value)])->save();
            }

            foreach (LessonBlock::query()->where('lesson_id', $lessonId)->whereNotNull('drip_weekday')->get() as $block) {
                $block->forceFill(['drip_weekday' => $shift($block->drip_weekday->value)])->save();
            }

            // As marcações saem e voltam com o dia novo: trocar uma a uma
            // esbarraria na chave única (pessoa, lição, dia, data) no meio do caminho.
            $checkins = DB::table('reading_checkins')->where('lesson_id', $lessonId)->get()
                ->map(fn (object $row) => [...(array) $row, 'weekday' => $shift((int) $row->weekday)])
                ->all();

            if ($checkins !== []) {
                DB::table('reading_checkins')->where('lesson_id', $lessonId)->delete();
                DB::table('reading_checkins')->insert($checkins);
            }
        }
    }
}
