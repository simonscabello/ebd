<?php

namespace App\Enums;

enum ClassroomRole: string
{
    case Teacher = 'teacher';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Teacher => 'Professor(a)',
            self::Student => 'Aluno(a)',
        };
    }
}
