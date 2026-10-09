<?php

namespace App\Console\Commands;

use App\Actions\Notifications\SendReadingReminders;
use App\Enums\ReminderSlot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ebd:remind-readings {slot=morning : morning (9h) ou evening (20h, só para quem ainda não leu)}')]
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

        $sent = $reminders->handle($slot);
        $this->components->info("Lembrete da leitura ({$slot->value}): {$sent} aparelho(s) avisado(s).");

        return self::SUCCESS;
    }
}
