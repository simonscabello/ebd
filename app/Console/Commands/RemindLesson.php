<?php

namespace App\Console\Commands;

use App\Actions\Notifications\SendLessonReminder;
use App\Models\Church;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ebd:remind-lesson {--due : só as igrejas em que agora são 8h (o agendamento roda a cada 15 minutos)}')]
#[Description('Véspera da EBD: envia o lembrete push da lição para as classes com encontro amanhã')]
class RemindLesson extends Command
{
    public function handle(SendLessonReminder $reminder): int
    {
        $churches = $this->option('due') ? Church::atLocalHour(SendLessonReminder::HOUR) : null;

        if ($churches !== null && $churches->isEmpty()) {
            return self::SUCCESS;
        }

        $sent = $reminder->handle(churches: $churches);
        $this->components->info("Lembrete da lição: {$sent} aparelho(s) avisado(s).");

        return self::SUCCESS;
    }
}
