<?php

namespace App\Enums;

/**
 * Os dois lembretes diários da leitura.
 */
enum ReminderSlot: string
{
    case Morning = 'morning';
    case Evening = 'evening';
}
