<?php

namespace App\Enums;

/**
 * Dia da semana no padrão ISO-8601 (o mesmo de Carbon::dayOfWeekIso).
 */
enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Segunda-feira',
            self::Tuesday => 'Terça-feira',
            self::Wednesday => 'Quarta-feira',
            self::Thursday => 'Quinta-feira',
            self::Friday => 'Sexta-feira',
            self::Saturday => 'Sábado',
            self::Sunday => 'Domingo',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Monday => 'Seg',
            self::Tuesday => 'Ter',
            self::Wednesday => 'Qua',
            self::Thursday => 'Qui',
            self::Friday => 'Sex',
            self::Saturday => 'Sáb',
            self::Sunday => 'Dom',
        };
    }

    /**
     * Plural para frases sobre o dia do encontro: "domingos", "quartas-feiras".
     */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Saturday => 'sábados',
            self::Sunday => 'domingos',
            default => str_replace('-feira', 's-feiras', mb_strtolower($this->label())),
        };
    }

    /** "Esta classe se reúne aos domingos." / "… às quartas-feiras." */
    public function meetingDayMessage(): string
    {
        $article = in_array($this, [self::Saturday, self::Sunday], true) ? 'aos' : 'às';

        return "Esta classe se reúne {$article} {$this->pluralLabel()}.";
    }
}
