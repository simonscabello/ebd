<?php

namespace App\Enums;

enum SelfAssessment: string
{
    case Correct = 'correct';
    case Partial = 'partial';
    case Wrong = 'wrong';

    public function label(): string
    {
        return match ($this) {
            self::Correct => 'Acertei',
            self::Partial => 'Em parte',
            self::Wrong => 'Errei',
        };
    }
}
