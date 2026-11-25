<?php

namespace App\Console\Commands;

use App\Actions\Notifications\SendAdventReminders;
use App\Models\Church;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ebd:remind-advent {--due : só as igrejas em que agora são 7h (o agendamento roda a cada 15 minutos)}')]
#[Description('Envia o lembrete push do capítulo do dia do Natal em Lucas (1º a 24 de dezembro)')]
class RemindAdvent extends Command
{
    public function handle(SendAdventReminders $reminders): int
    {
        $churches = $this->option('due') ? Church::atLocalHour(SendAdventReminders::HOUR) : null;

        if ($churches !== null && $churches->isEmpty()) {
            return self::SUCCESS;
        }

        $sent = $reminders->handle(churches: $churches);
        $this->components->info("Natal em Lucas: {$sent} aparelho(s) avisado(s).");

        return self::SUCCESS;
    }
}
