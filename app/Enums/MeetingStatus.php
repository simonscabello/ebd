<?php

namespace App\Enums;

/**
 * Situação de um encontro (domingo) da classe.
 */
enum MeetingStatus: string
{
    case Planned = 'planned';
    case Held = 'held';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planejado',
            self::Held => 'Realizado',
            self::Cancelled => 'Sem EBD',
        };
    }
}
