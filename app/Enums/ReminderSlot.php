<?php

namespace App\Enums;

/**
 * Os dois lembretes diários da leitura.
 */
enum ReminderSlot: string
{
    case Morning = 'morning';
    case Evening = 'evening';

    /** Hora local, no fuso de cada igreja. */
    public function hour(): int
    {
        return match ($this) {
            self::Morning => 9,
            self::Evening => 20,
        };
    }
}
