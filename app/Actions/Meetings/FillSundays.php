<?php

namespace App\Actions\Meetings;

use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Support\ChurchCalendar;
use Carbon\CarbonImmutable;

/**
 * Todo domingo da classe existe na agenda: do primeiro encontro (ou do domingo
 * desta semana, numa classe nova) até algumas semanas à frente. Domingo sem
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
        $today = ChurchCalendar::today();
        $thisSunday = $today->isSunday() ? $today : $today->previous(CarbonImmutable::SUNDAY);
        $until = $thisSunday->addWeeks(self::WEEKS_AHEAD)->toDateString();

        $first = ClassMeeting::query()->whereBelongsTo($classroom)->min('held_on');
        $sunday = CarbonImmutable::parse($first !== null ? substr((string) $first, 0, 10) : $thisSunday->toDateString());
        $sunday = $sunday->isSunday() ? $sunday : $sunday->next(CarbonImmutable::SUNDAY);

        $existing = array_flip(ClassMeeting::query()
            ->whereBelongsTo($classroom)
            ->whereDate('held_on', '>=', $sunday->toDateString())
            ->pluck('held_on')
            ->map(fn ($date) => $date->toDateString())
            ->all());

        $rows = [];
        $now = now();

        for (; $sunday->toDateString() <= $until; $sunday = $sunday->addWeek()) {
            if (! isset($existing[$sunday->toDateString()])) {
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
