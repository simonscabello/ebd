<?php

namespace App\Enums;

/**
 * Selos pessoais. Cada aluno vê só os próprios; não há ranking.
 */
enum Badge: string
{
    case FirstFullWeek = 'first_full_week';
    case Streak7 = 'streak_7';
    case Streak30 = 'streak_30';
    case FaithfulReader = 'faithful_reader';
    case PerfectAttendance = 'perfect_attendance';

    /**
     * @param  Weekday  $meetingDay  dia de encontro da turma: "domingos" para
     *                               quem se reúne no domingo, "encontros" nos outros
     */
    public function label(Weekday $meetingDay = Weekday::Sunday): string
    {
        return match ($this) {
            self::FirstFullWeek => 'Primeira semana completa',
            self::Streak7 => '7 dias seguidos',
            self::Streak30 => '30 dias seguidos',
            self::FaithfulReader => 'Leitor fiel',
            self::PerfectAttendance => "Presença em todos os {$meetingDay->meetingNounPlural()}",
        };
    }

    public function description(Weekday $meetingDay = Weekday::Sunday): string
    {
        return match ($this) {
            self::FirstFullWeek => 'Leu as leituras de todos os dias antes do encontro de uma lição.',
            self::Streak7 => 'Uma semana inteira sem deixar de ler.',
            self::Streak30 => 'Um mês de leitura diária. Que constância!',
            self::FaithfulReader => 'Estudou durante a semana em quase todas as lições da série.',
            self::PerfectAttendance => "Esteve em todos os {$meetingDay->meetingNounPlural()} da série.",
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::FirstFullWeek => '📖',
            self::Streak7 => '🔥',
            self::Streak30 => '🌟',
            self::FaithfulReader => '🏅',
            self::PerfectAttendance => '⛪',
        };
    }

    /** Selo conquistado uma vez por série (trimestre). */
    public function isPerSeries(): bool
    {
        return in_array($this, [self::FaithfulReader, self::PerfectAttendance], true);
    }
}
