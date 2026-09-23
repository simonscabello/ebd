<?php

namespace App\Enums;

enum LessonVisibility: string
{
    /** Qualquer pessoa com o link abre a lição publicada, sem login. */
    case Public = 'public';

    /** Somente membros da classe (e administradores) autenticados. */
    case Members = 'members';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Pública (qualquer pessoa com o link)',
            self::Members => 'Somente membros da classe',
        };
    }
}
