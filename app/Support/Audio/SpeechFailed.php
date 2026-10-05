<?php

namespace App\Support\Audio;

use RuntimeException;
use Throwable;

/**
 * Falha na geração do áudio. A mensagem é a que aparece para o professor;
 * o detalhe técnico fica na exceção anterior (e no log).
 */
class SpeechFailed extends RuntimeException
{
    public static function because(string $message, ?Throwable $previous = null): self
    {
        return new self($message, 0, $previous);
    }
}
