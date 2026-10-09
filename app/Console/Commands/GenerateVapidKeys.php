<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

#[Signature('ebd:vapid-keys')]
#[Description('Gera um par de chaves VAPID para as notificações push')]
class GenerateVapidKeys extends Command
{
    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->components->info('Copie para o .env (ou para as variáveis do Railway):');
        $this->newLine();
        $this->line("VAPID_PUBLIC_KEY={$keys['publicKey']}");
        $this->line("VAPID_PRIVATE_KEY={$keys['privateKey']}");
        $this->newLine();
        $this->components->warn('A chave privada é secreta. Trocar as chaves invalida as inscrições existentes.');

        return self::SUCCESS;
    }
}
