<?php

namespace App\Enums;

enum QuestionKind: string
{
    /** Pergunta aberta para pensar durante a semana e discutir no domingo. */
    case Reflection = 'reflection';

    /** Pergunta de revisão com gabarito, para o aluno conferir o que aprendeu. */
    case Review = 'review';

    public function label(): string
    {
        return match ($this) {
            self::Reflection => 'Reflexão',
            self::Review => 'Revisão (com gabarito)',
        };
    }
}
