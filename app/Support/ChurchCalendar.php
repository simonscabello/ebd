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
     * Hoje como Y-m-d. Datas do banco (held_on, read_on) são comparadas assim,
     * como texto, nunca como instantes: meia-noite UTC não é meia-noite aqui.
     */
    public static function todayString(): string
    {
        return self::today()->toDateString();
    }

    /**
     * Dias de calendário entre duas datas (negativo se $to vem antes).
     */
    public static function daysBetween(string|\DateTimeInterface $from, string|\DateTimeInterface $to): int
    {
        $day = fn (string|\DateTimeInterface $date) => CarbonImmutable::parse(
            is_string($date) ? substr($date, 0, 10) : $date->format('Y-m-d'),
            'UTC',
        );

        return (int) $day($from)->diffInDays($day($to), false);
    }

    /**
     * Data de entrada do aluno na classe no fuso da igreja: o created_at do
     * vínculo é gravado em UTC. Use com o fuso como binding (?).
     */
    public const JOINED_ON_SQL = "((classroom_user.created_at AT TIME ZONE 'UTC') AT TIME ZONE ?)::date";

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
        return self::localized($date)->translatedFormat('l, j \d\e F \d\e Y');
    }

    public static function formatShort(\DateTimeInterface $date): string
    {
        return self::localized($date)->translatedFormat('j \d\e M \d\e Y');
    }

    public static function monthShort(\DateTimeInterface $date): string
    {
        return self::localized($date)->translatedFormat('M');
    }

    private static function localized(\DateTimeInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->format('Y-m-d'))->settings(['locale' => 'pt_BR']);
    }
}
