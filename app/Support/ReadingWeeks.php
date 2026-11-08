<?php

namespace App\Support;

use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use Illuminate\Database\Query\Builder;

/**
 * Semanas de leitura de uma lição. Uma lição que continua no domingo seguinte
 * tem um encontro por semana, e cada semana de leitura (os seis dias antes de
 * cada encontro até o dia dele) é uma semana própria: a leitura de segunda da
 * 2ª semana é marcada de novo, conta de novo no progresso da semana e na
 * sequência de dias, e o lembrete da noite só olha a semana de hoje.
 *
 * As semanas são recortadas pela data em que a pessoa marcou (read_on): a do
 * encontro k vai do dia seguinte ao encontro k-1 até o encontro k. A primeira
 * não tem começo (adiantar a leitura conta nela) e a última não tem fim (pôr
 * em dia depois da aula conta nela). Lição de um encontro só tem uma semana
 * sem limites: tudo o que foi marcado vale, como sempre foi. Um domingo "sem
 * EBD" também fecha uma semana: a lição atual é lida de novo nele e de novo na
 * semana do encontro seguinte.
 */
final class ReadingWeeks
{
    /**
     * Datas (Y-m-d) que fecham cada semana de leitura de cada lição, em ordem:
     * os encontros não cancelados dela e os domingos "sem EBD" logo antes de
     * cada um (a lição atual seguiu sendo lida naquela semana, e o encontro
     * cancelado já não guarda a lição).
     *
     * @param  list<int>  $lessonIds
     * @return array<int, list<string>>
     */
    public static function meetingDates(array $lessonIds): array
    {
        if ($lessonIds === []) {
            return [];
        }

        $dates = array_fill_keys($lessonIds, []);

        $meetings = ClassMeeting::query()
            ->whereIn('classroom_id', ClassMeeting::query()->whereIn('lesson_id', $lessonIds)->select('classroom_id'))
            ->where(fn ($q) => $q->whereNotNull('lesson_id')->orWhere('status', MeetingStatus::Cancelled))
            ->chronological()
            ->get(['classroom_id', 'lesson_id', 'status', 'held_on']);

        foreach ($meetings->groupBy('classroom_id') as $classroomMeetings) {
            $cancelled = [];

            foreach ($classroomMeetings as $meeting) {
                if ($meeting->isCancelled()) {
                    $cancelled[] = $meeting->held_on->toDateString();

                    continue;
                }

                if (isset($dates[(int) $meeting->lesson_id])) {
                    array_push($dates[(int) $meeting->lesson_id], ...$cancelled, ...[$meeting->held_on->toDateString()]);
                }

                $cancelled = [];
            }
        }

        return array_map(fn (array $list) => array_values(array_unique($list)), $dates);
    }

    /**
     * Índice da semana (0 = 1º encontro) em que cai a data.
     *
     * @param  list<string>  $meetingDates
     */
    public static function index(array $meetingDates, string $date): int
    {
        foreach ($meetingDates as $i => $meetingOn) {
            if ($date <= $meetingOn) {
                return $i;
            }
        }

        return max(count($meetingDates) - 1, 0);
    }

    /**
     * Limites da semana em que cai a data: [depois de, até] (Y-m-d ou null).
     *
     * @param  list<string>  $meetingDates
     * @return array{0: string|null, 1: string|null}
     */
    public static function bounds(array $meetingDates, string $date): array
    {
        $i = self::index($meetingDates, $date);
        $last = count($meetingDates) - 1;

        return [
            $i > 0 ? $meetingDates[$i - 1] : null,
            $i < $last ? $meetingDates[$i] : null,
        ];
    }

    /**
     * Restringe uma consulta de reading_checkins de UMA lição à semana de
     * leitura em que cai a data (hoje, se omitida).
     */
    public static function constrain(Builder $query, int $lessonId, ?string $date = null, string $column = 'read_on'): Builder
    {
        $date ??= ChurchCalendar::todayString();
        [$after, $until] = self::bounds(self::meetingDates([$lessonId])[$lessonId], $date);

        return $query
            ->when($after !== null, fn ($q) => $q->where($column, '>', $after))
            ->when($until !== null, fn ($q) => $q->where($column, '<=', $until));
    }
}
