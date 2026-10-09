<?php

namespace App\Console\Commands;

use App\Actions\Notifications\SendLessonReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ebd:remind-lesson')]
#[Description('Véspera da EBD: envia o lembrete push da lição para as classes com encontro amanhã')]
class RemindLesson extends Command
{
    public function handle(SendLessonReminder $reminder): int
    {
        $sent = $reminder->handle();
        $this->components->info("Lembrete da lição: {$sent} aparelho(s) avisado(s).");

        return self::SUCCESS;
    }
}
