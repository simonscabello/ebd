<?php

namespace App\Actions\Meetings;

use App\Actions\Lessons\SyncLessonSchedule;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Support\ChurchCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Os encontros seguem a lição atual da classe (classrooms.current_lesson_id).
 *
 * O próximo encontro em aberto (hoje ou depois, planejado, sem chamada, não
 * encerrado e sem título) recebe a lição atual; sem nenhum em aberto, um é
 * criado no primeiro dia de encontro livre a partir de hoje. Os em aberto
 * mais adiante perdem a lição: a sequência vem da série, não da agenda.
 * Encontro com chamada, encerrado, realizado, "sem EBD" ou com título (evento
 * ou aula especial que o professor nomeou) não muda.
 *
 * Fora de uma requisição, chame dentro do fuso da igreja (ChurchCalendar::using).
 */
class SyncUpcomingMeeting
{
    public function __construct(private readonly SyncLessonSchedule $syncSchedule) {}

    public function handle(Classroom $classroom): void
    {
        if (! $classroom->holdsMeetings()) {
            return;
        }

        DB::transaction(function () use ($classroom) {
            $classroom = Classroom::query()->lockForUpdate()->findOrFail($classroom->id);
            $lessonId = $classroom->current_lesson_id;
            $today = ChurchCalendar::today();

            $open = ClassMeeting::query()
                ->whereBelongsTo($classroom)
                ->where('status', MeetingStatus::Planned)
                ->whereDate('held_on', '>=', $today->toDateString())
                ->whereNull('attendance_taken_at')
                ->whereNull('finished_at')
                ->whereNull('title')
                ->chronological()
                // A chamada trava o encontro: esperar por ela e reler o estado.
                ->lockForUpdate()
                ->get();

            $next = $open->first();

            if ($next === null && $lessonId !== null) {
                ClassMeeting::query()->create([
                    'classroom_id' => $classroom->id,
                    'held_on' => $this->freeDay($classroom, $today),
                    'lesson_id' => $lessonId,
                ]);
            } elseif ($next !== null && $next->lesson_id !== $lessonId) {
                $next->update(['lesson_id' => $lessonId]);
            }

            ClassMeeting::query()
                ->whereKey($open->skip(1)->modelKeys())
                ->whereNotNull('lesson_id')
                ->update(['lesson_id' => null]);

            $this->syncSchedule->handle($classroom);
        });
    }

    /**
     * Primeiro dia de encontro, a partir de hoje, numa semana sem encontro
     * (realizado, "sem EBD" ou evento ocupam a semana).
     */
    private function freeDay(Classroom $classroom, CarbonImmutable $today): string
    {
        $taken = ClassMeeting::query()
            ->whereBelongsTo($classroom)
            ->whereDate('held_on', '>=', $today->subDays(6)->toDateString())
            ->pluck('held_on')
            ->map(fn ($date) => $date->toDateString())
            ->all();

        $day = ChurchCalendar::onOrAfter($today, $classroom->meeting_weekday);

        $weekTaken = fn (CarbonImmutable $day) => array_filter(
            $taken,
            fn (string $d) => $d >= $day->subDays(6)->toDateString() && $d <= $day->toDateString(),
        ) !== [];

        // Termina: as datas ocupadas são finitas.
        while ($weekTaken($day)) {
            $day = $day->addWeek();
        }

        return $day->toDateString();
    }
}
