<?php

namespace App\Console\Commands;

use App\Actions\Notifications\SendReadingReminders;
use App\Enums\ReminderSlot;
use App\Models\Church;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ebd:remind-readings {slot=morning : morning (9h) ou evening (20h, só para quem ainda não leu)} {--due : só as igrejas em que agora é a hora do lembrete (o agendamento roda a cada 15 minutos)}')]
#[Description('Envia o lembrete push da leitura do dia para as classes')]
class RemindReadings extends Command
{
    public function handle(SendReadingReminders $reminders): int
    {
        $slot = ReminderSlot::tryFrom((string) $this->argument('slot'));

        if ($slot === null) {
            $this->components->error('Use "morning" ou "evening".');

            return self::INVALID;
        }

        $churches = $this->option('due') ? Church::atLocalHour($slot->hour()) : null;

        if ($churches !== null && $churches->isEmpty()) {
            return self::SUCCESS;
        }

        $sent = $reminders->handle($slot, churches: $churches);
        $this->components->info("Lembrete da leitura ({$slot->value}): {$sent} aparelho(s) avisado(s).");

        return self::SUCCESS;
    }
}
