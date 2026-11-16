<?php

namespace App\Enums;

enum AiUsageKind: string
{
    /** Uma dúvida respondida pelo "Tirar dúvida". */
    case Question = 'question';

    /** Um áudio do estudo gerado. */
    case Audio = 'audio';

    /** Uma sequência de leituras escrita para a lição que continuou. */
    case Readings = 'readings';
}
