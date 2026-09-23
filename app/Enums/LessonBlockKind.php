<?php

namespace App\Enums;

/**
 * Tipos de bloco de conteúdo de uma lição, além do estudo principal.
 * Os quatro primeiros são material de preparação do professor.
 */
enum LessonBlockKind: string
{
    case Roteiro = 'roteiro';
    case ExtraTime = 'extra_time';
    case AccuracyNote = 'accuracy_note';
    case TeacherNote = 'teacher_note';
    case Context = 'context';
    case Theology = 'theology';
    case Curiosity = 'curiosity';
    case Application = 'application';
    case Concept = 'concept';

    public function label(): string
    {
        return match ($this) {
            self::Roteiro => 'Roteiro da aula',
            self::ExtraTime => 'Se houver tempo',
            self::AccuracyNote => 'Nota de precisão',
            self::TeacherNote => 'Notas do professor',
            self::Context => 'Contexto histórico e cultural',
            self::Theology => 'Análise teológica',
            self::Curiosity => 'Curiosidade',
            self::Application => 'Aplicação prática',
            self::Concept => 'Conceito',
        };
    }

    public function defaultAudience(): ContentAudience
    {
        return match ($this) {
            self::Roteiro, self::ExtraTime, self::AccuracyNote, self::TeacherNote => ContentAudience::Teacher,
            default => ContentAudience::Student,
        };
    }

    /**
     * Pode ser liberado aos poucos em "Minha semana" (um por dia).
     */
    public function isDrippable(): bool
    {
        return in_array($this, [self::Curiosity, self::Concept, self::Context, self::Application], true);
    }
}
