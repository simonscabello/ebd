<?php

namespace App\Actions\Meetings;

use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Support\ChurchCalendar;
use Carbon\CarbonImmutable;

/**
 * Todo dia de encontro da turma (o dia da semana dela; domingo por padrão)
 * existe na agenda: do primeiro encontro (ou do encontro desta semana, numa
 * turma nova) até algumas semanas à frente. Domingo sem
 * aula não é apagado, é marcado como "sem EBD" (CancelMeeting).
 *
 * Só cria o que falta; a chave única (classroom_id, held_on) segura duas
 * chamadas ao mesmo tempo.
 */
class FillSundays
{
    /** Quantas semanas à frente de hoje a agenda fica preenchida. */
    public const WEEKS_AHEAD = 12;

    /**
     * @return int domingos criados
     */
    public function handle(Classroom $classroom): int
    {
        $weekday = $classroom->meeting_weekday;
        $thisWeek = ChurchCalendar::onOrBefore(ChurchCalendar::today(), $weekday);
        $until = $thisWeek->addWeeks(self::WEEKS_AHEAD)->toDateString();

        $first = ClassMeeting::query()->whereBelongsTo($classroom)->min('held_on');
        $sunday = ChurchCalendar::onOrAfter(
            CarbonImmutable::parse($first !== null ? substr((string) $first, 0, 10) : $thisWeek->toDateString()),
            $weekday,
        );

        $existing = ClassMeeting::query()
            ->whereBelongsTo($classroom)
            ->whereDate('held_on', '>=', $sunday->subDays(6)->toDateString())
            ->pluck('held_on')
            ->map(fn ($date) => $date->toDateString())
            ->all();

        $rows = [];
        $now = now();

        for (; $sunday->toDateString() <= $until; $sunday = $sunday->addWeek()) {
            // Uma semana que já tem encontro (mesmo em outro dia, como os
            // antigos de uma turma que trocou de dia) não ganha outro.
            $weekStart = $sunday->subDays(6)->toDateString();
            $weekHasMeeting = array_filter($existing, fn (string $d) => $d >= $weekStart && $d <= $sunday->toDateString()) !== [];

            if (! $weekHasMeeting) {
                $rows[] = [
                    'classroom_id' => $classroom->id,
                    'held_on' => $sunday->toDateString(),
                    'status' => 'planned',
                    'visitors_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $rows === [] ? 0 : ClassMeeting::query()->insertOrIgnore($rows);
    }
}
