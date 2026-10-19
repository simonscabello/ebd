<?php

namespace App\Enums;

enum AiUsageKind: string
{
    /** Uma dúvida respondida pelo "Tirar dúvida". */
    case Question = 'question';

    /** Um áudio do estudo gerado. */
    case Audio = 'audio';
}
