<?php

namespace App\Enums;

/**
 * Para quem um bloco de conteúdo ou material é exibido.
 * Conteúdo "teacher" nunca é enviado a alunos/visitantes nem indexado na busca.
 */
enum ContentAudience: string
{
    case Teacher = 'teacher';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Teacher => 'Só professor',
            self::Student => 'Alunos',
        };
    }
}
