<?php

namespace App\Support;

use App\Models\Church;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Natal em Lucas: um capítulo do Evangelho por dia, de 1º a 24 de dezembro
 * (dia N = Lucas N). Vale para todas as igrejas, sem cadastro: o plano é
 * este arquivo. Até 31/12 dá para pôr em dia os capítulos que ficaram.
 */
final class LukeAdvent
{
    public const DAYS = 24;

    /** Dias antes do início em que a home já anuncia o especial. */
    private const TEASER_DAYS = 7;

    /** @var list<array{from: int, to: int, title: string}> */
    public const BLOCKS = [
        ['from' => 1, 'to' => 2, 'title' => 'O nascimento de João Batista e de Jesus'],
        ['from' => 3, 'to' => 9, 'title' => 'Batismo e ministério na Galileia'],
        ['from' => 10, 'to' => 19, 'title' => 'A caminho de Jerusalém'],
        ['from' => 20, 'to' => 24, 'title' => 'Jerusalém, a cruz e a ressurreição'],
    ];

    public static function startsOn(?CarbonImmutable $today = null): CarbonImmutable
    {
        $today ??= ChurchCalendar::today();
        // Só para testar fora de dezembro (EBD_ADVENT_STARTS_ON).
        $override = config('ebd.advent.starts_on');

        return $override
            ? CarbonImmutable::parse((string) $override, $today->timezone)->startOfDay()
            : $today->setDate($today->year, 12, 1);
    }

    public static function year(?CarbonImmutable $today = null): int
    {
        return self::startsOn($today)->year;
    }

    /** Posição de hoje no especial: 1 no primeiro dia, 0 ou menos antes dele. */
    private static function index(?CarbonImmutable $today = null): int
    {
        $today ??= ChurchCalendar::today();

        return ChurchCalendar::daysBetween(self::startsOn($today), $today) + 1;
    }

    /** O capítulo de hoje (1 a 24); null fora dos 24 dias. */
    public static function dayFor(?CarbonImmutable $today = null): ?int
    {
        $index = self::index($today);

        return $index >= 1 && $index <= self::DAYS ? $index : null;
    }

    /** Do primeiro dia até uma semana depois do último (31/12). */
    public static function isOpen(?CarbonImmutable $today = null): bool
    {
        $index = self::index($today);

        return $index >= 1 && $index <= self::DAYS + 7;
    }

    public static function isUpcoming(?CarbonImmutable $today = null): bool
    {
        $index = self::index($today);

        return $index <= 0 && $index > -self::TEASER_DAYS;
    }

    /** Até que capítulo já dá para marcar hoje (0 antes de começar). */
    public static function unlockedUntil(?CarbonImmutable $today = null): int
    {
        return self::isOpen($today) ? min(self::index($today), self::DAYS) : 0;
    }

    public static function reference(int $day): string
    {
        return "Lucas {$day}";
    }

    /**
     * @return list<int> capítulos que a pessoa já marcou neste ano
     */
    public static function readDays(User $user, ?CarbonImmutable $today = null): array
    {
        return array_values(DB::table('advent_checkins')
            ->where('user_id', $user->id)
            ->where('year', self::year($today))
            ->orderBy('day')
            ->pluck('day')
            ->map(fn ($day) => (int) $day)
            ->all());
    }

    /**
     * Quantas pessoas da igreja já leram cada capítulo (só o número).
     *
     * @return array<int, int> dia => leitores
     */
    public static function readers(?Church $church, ?CarbonImmutable $today = null): array
    {
        if ($church === null) {
            return [];
        }

        return DB::table('advent_checkins')
            ->where('year', self::year($today))
            ->whereIn('user_id', DB::table('classroom_user')
                ->whereIn('classroom_id', DB::table('classrooms')->where('church_id', $church->id)->select('id'))
                ->select('user_id'))
            ->groupBy('day')
            ->selectRaw('day, COUNT(*) AS readers')
            ->pluck('readers', 'day')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * O card do Início; null quando não é época.
     *
     * @return array{state: 'upcoming'|'open', starts_label: string, day: int|null, reference: string|null, today_done: bool, read: int, total: int}|null
     */
    public static function card(User $user, ?CarbonImmutable $today = null): ?array
    {
        if (! self::isOpen($today) && ! self::isUpcoming($today)) {
            return null;
        }

        $day = self::dayFor($today);
        $read = self::readDays($user, $today);

        return [
            'state' => self::isOpen($today) ? 'open' : 'upcoming',
            'starts_label' => ChurchCalendar::formatShort(self::startsOn($today)),
            'day' => $day,
            'reference' => $day ? self::reference($day) : null,
            'today_done' => $day !== null && in_array($day, $read, true),
            'read' => count($read),
            'total' => self::DAYS,
        ];
    }
}
