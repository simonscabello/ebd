<?php

namespace App\Support;

use App\Enums\Weekday;
use App\Models\Church;
use Carbon\CarbonImmutable;

/**
 * Datas "do ponto de vista da igreja". O servidor e o banco trabalham em UTC,
 * mas "hoje", "próximo domingo" e a saudação dependem do fuso da igreja.
 *
 * O fuso é o da igreja em contexto: numa requisição, o middleware
 * UseChurchTimezone escolhe a igreja da classe ou lição da rota (ou a da
 * pessoa); nos lembretes e tarefas agendadas, using() envolve cada igreja.
 * Sem igreja em contexto (visitante, console), vale o da configuração.
 */
class ChurchCalendar
{
    private static ?string $timezone = null;

    public static function timezone(): string
    {
        return self::$timezone ?? (string) config('ebd.timezone');
    }

    /**
     * Fixa o fuso em contexto (null volta ao da configuração).
     */
    public static function setTimezone(Church|string|null $timezone): void
    {
        self::$timezone = $timezone instanceof Church ? $timezone->timezone : $timezone;
    }

    /**
     * Roda $callback no fuso da igreja e restaura o anterior, mesmo com erro.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function using(Church|string|null $timezone, callable $callback): mixed
    {
        $previous = self::$timezone;
        self::setTimezone($timezone);

        try {
            return $callback();
        } finally {
            self::$timezone = $previous;
        }
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
     * Dia do plano de leitura (1 = segunda ... 7 = domingo) que cai hoje na
     * semana de leitura de um encontro: dos seis dias antes até o dia do
     * encontro. Fora dessa semana não há "leitura de hoje": no dia da aula, a
     * leitura daquele dia da lição seguinte ainda não começou.
     */
    public static function readingWeekday(?\DateTimeInterface $meetingOn, ?\DateTimeInterface $today = null): ?int
    {
        if ($meetingOn === null) {
            return null;
        }

        $today = CarbonImmutable::parse(($today ?? self::today())->format('Y-m-d'), self::timezone());
        $end = CarbonImmutable::parse($meetingOn->format('Y-m-d'), self::timezone());

        return $today->betweenIncluded(self::readingWeekStart($end), $end) ? $today->dayOfWeekIso : null;
    }

    /**
     * Primeiro dia da semana de leitura: seis dias antes do encontro (para
     * quem se reúne no domingo, a segunda-feira).
     */
    public static function readingWeekStart(\DateTimeInterface $meetingOn): CarbonImmutable
    {
        return CarbonImmutable::parse($meetingOn->format('Y-m-d'), self::timezone())->subDays(6);
    }

    /**
     * Próximo dia de encontro (hoje, se hoje for o dia).
     */
    public static function nextMeetingDay(Weekday $weekday): CarbonImmutable
    {
        return self::onOrAfter(self::today(), $weekday);
    }

    /**
     * O primeiro $weekday a partir da data, inclusive.
     */
    public static function onOrAfter(\DateTimeInterface $date, Weekday $weekday): CarbonImmutable
    {
        $day = CarbonImmutable::parse($date->format('Y-m-d'), self::timezone());

        return $day->addDays(($weekday->value - $day->dayOfWeekIso + 7) % 7);
    }

    /**
     * O último $weekday até a data, inclusive (o encontro "desta semana").
     */
    public static function onOrBefore(\DateTimeInterface $date, Weekday $weekday): CarbonImmutable
    {
        $day = CarbonImmutable::parse($date->format('Y-m-d'), self::timezone());

        return $day->subDays(($day->dayOfWeekIso - $weekday->value + 7) % 7);
    }

    /** De manhã, o dia da semana ("Feliz terça-feira"); depois, tarde, noite e madrugada. */
    public static function greeting(): string
    {
        $now = self::now();

        return match (true) {
            $now->hour < 5 => 'Boa madrugada',
            $now->hour < 12 => 'Feliz '.Weekday::from($now->dayOfWeekIso)->lowerLabel(),
            $now->hour < 18 => 'Boa tarde',
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
