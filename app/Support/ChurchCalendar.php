<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Datas "do ponto de vista da igreja". O servidor e o banco trabalham em UTC,
 * mas "hoje", "próximo domingo" e a saudação dependem do fuso da igreja.
 */
class ChurchCalendar
{
    public static function timezone(): string
    {
        return (string) config('ebd.timezone');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }

    /**
     * Próximo domingo (hoje, se hoje for domingo).
     */
    public static function nextSunday(): CarbonImmutable
    {
        $today = self::today();

        return $today->isSunday() ? $today : $today->next(CarbonImmutable::SUNDAY);
    }

    public static function greeting(): string
    {
        $hour = self::now()->hour;

        return match (true) {
            $hour < 5 => 'Boa noite',
            $hour < 12 => 'Bom dia',
            $hour < 18 => 'Boa tarde',
            default => 'Boa noite',
        };
    }

    /**
     * Dias entre hoje e a data (negativo se já passou).
     */
    public static function daysUntil(\DateTimeInterface $date): int
    {
        $target = CarbonImmutable::parse($date->format('Y-m-d'), self::timezone())->startOfDay();

        return (int) self::today()->diffInDays($target, false);
    }

    public static function formatLong(\DateTimeInterface $date): string
    {
        return CarbonImmutable::parse($date->format('Y-m-d'))
            ->locale('pt_BR')
            ->translatedFormat('l, j \d\e F \d\e Y');
    }

    public static function formatShort(\DateTimeInterface $date): string
    {
        return CarbonImmutable::parse($date->format('Y-m-d'))
            ->locale('pt_BR')
            ->translatedFormat('j \d\e M \d\e Y');
    }
}
