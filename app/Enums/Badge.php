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
    case FirstStep = 'first_step';
    case NoteTaker = 'note_taker';
    case Berean = 'berean';
    case Comeback = 'comeback';
    case NightOwl = 'night_owl';
    case EarlyBird = 'early_bird';

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
            self::FirstStep => 'Primeiro passo',
            self::NoteTaker => 'Anotador',
            self::Berean => 'Bereano',
            self::Comeback => 'De volta',
            self::NightOwl => 'Coruja noturna',
            self::EarlyBird => 'Madrugador',
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
            self::FirstStep => 'Marcou a primeira leitura.',
            self::NoteTaker => 'Fez anotações em 5 lições.',
            self::Berean => 'Fez 10 perguntas no Tirar dúvida, como os bereanos, que examinavam as Escrituras (At 17.11).',
            self::Comeback => 'Voltou a ler depois de duas semanas sem leitura. Que bom ter você de volta!',
            self::NightOwl => 'Marcou leituras depois das 22h em 5 dias diferentes.',
            self::EarlyBird => 'Marcou leituras antes das 7h em 5 dias diferentes.',
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
            self::FirstStep => '👣',
            self::NoteTaker => '✍️',
            self::Berean => '📜',
            self::Comeback => '🌱',
            self::NightOwl => '🦉',
            self::EarlyBird => '🌅',
        };
    }

    /**
     * Selo surpresa: só aparece depois de conquistado. Não vira meta (ninguém
     * deve parar de ler para ganhar o "De volta").
     */
    public function isSurprise(): bool
    {
        return in_array($this, [self::Comeback, self::NightOwl, self::EarlyBird], true);
    }

    /** Selo conquistado uma vez por série (trimestre). */
    public function isPerSeries(): bool
    {
        return in_array($this, [self::FaithfulReader, self::PerfectAttendance], true);
    }
}
