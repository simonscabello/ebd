<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Sequência de dias de estudo: dias com leitura marcada ("Li hoje") e
 * domingos com presença. A sequência atual continua viva se o último dia
 * foi hoje ou ontem (dá tempo de ler hoje).
 */
class StudyStreak
{
    /**
     * @return array{current: int, best: int, today_done: bool}
     */
    public function for(User $user, ?CarbonImmutable $today = null): array
    {
        $today ??= ChurchCalendar::today();
        $dates = $this->dates($user, $today->subDays(400));

        return self::compute($dates, $today);
    }

    /**
     * @param  list<string>  $dates  datas Y-m-d (qualquer ordem, podem repetir)
     * @return array{current: int, best: int, today_done: bool}
     */
    public static function compute(array $dates, CarbonImmutable $today): array
    {
        $set = array_fill_keys($dates, true);
        ksort($set);

        $best = 0;
        $run = 0;
        $previous = null;

        foreach (array_keys($set) as $date) {
            $day = CarbonImmutable::parse($date);
            $run = $previous !== null && $previous->addDay()->isSameDay($day) ? $run + 1 : 1;
            $best = max($best, $run);
            $previous = $day;
        }

        $todayDone = isset($set[$today->toDateString()]);
        $cursor = $todayDone ? $today : $today->subDay();
        $current = 0;

        while (isset($set[$cursor->toDateString()])) {
            $current++;
            $cursor = $cursor->subDay();
        }

        return ['current' => $current, 'best' => $best, 'today_done' => $todayDone];
    }

    /**
     * @return list<string>
     */
    public function dates(User $user, CarbonImmutable $since): array
    {
        $readings = DB::table('reading_checkins')
            ->where('user_id', $user->id)
            ->where('read_on', '>=', $since->toDateString())
            ->distinct()
            ->pluck('read_on');

        $attended = DB::table('attendances')
            ->join('class_meetings', 'class_meetings.id', '=', 'attendances.class_meeting_id')
            ->where('attendances.user_id', $user->id)
            ->where('class_meetings.held_on', '>=', $since->toDateString())
            ->pluck('class_meetings.held_on');

        return array_values(array_unique(array_map(
            fn ($d) => substr((string) $d, 0, 10),
            [...$readings->all(), ...$attended->all()],
        )));
    }
}
