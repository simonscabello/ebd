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

    /** "domingo", "quarta-feira": para frases como "faltam 3 dias para quarta-feira". */
    public function lowerLabel(): string
    {
        return mb_strtolower($this->label());
    }

    /**
     * Nome do encontro na interface: quem se reúne no domingo continua vendo
     * "Domingo" (Modo Domingo); os outros dias, "Encontro".
     */
    public function meetingName(): string
    {
        return $this === self::Sunday ? 'Domingo' : 'Encontro';
    }

    public function meetingNamePlural(): string
    {
        return $this === self::Sunday ? 'Domingos' : 'Encontros';
    }

    /** "domingos" / "encontros", no meio da frase. */
    public function meetingNounPlural(): string
    {
        return mb_strtolower($this->meetingNamePlural());
    }

    /** "domingo" / "encontro", no meio da frase. */
    public function meetingNoun(): string
    {
        return mb_strtolower($this->meetingName());
    }

    /** "no domingo", "na quarta-feira". */
    public function onDayPhrase(): string
    {
        return in_array($this, [self::Saturday, self::Sunday], true)
            ? "no {$this->lowerLabel()}"
            : "na {$this->lowerLabel()}";
    }

    /**
     * O mesmo dia do plano de leitura numa turma que se reúne em outro dia:
     * anda o deslocamento entre os dois dias de encontro. De domingo para
     * quarta, a segunda vira quinta e o domingo (dia da aula) vira quarta.
     */
    public function shiftedBetween(self $fromMeeting, self $toMeeting): self
    {
        $offset = $toMeeting->value - $fromMeeting->value;

        return self::from((($this->value - 1 + $offset) % 7 + 7) % 7 + 1);
    }

    /** "no próximo domingo", "na próxima quarta-feira". */
    public function nextMeetingPhrase(): string
    {
        return in_array($this, [self::Saturday, self::Sunday], true)
            ? "no próximo {$this->lowerLabel()}"
            : "na próxima {$this->lowerLabel()}";
    }
}
