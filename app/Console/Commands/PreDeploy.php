<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Pre-deploy command do Railway: roda uma vez por deploy, antes de a nova
 * versão receber tráfego. Se as migrations falharem, o deploy é abortado.
 */
#[Signature('ebd:predeploy')]
#[Description('Executa as migrations e o bootstrap de administradores (pre-deploy)')]
class PreDeploy extends Command
{
    public function handle(): int
    {
        $migrated = $this->call('migrate', ['--force' => true]);

        if ($migrated !== self::SUCCESS) {
            $this->components->error('Falha nas migrations: deploy abortado.');

            return self::FAILURE;
        }

        return $this->call('ebd:promote-admins');
    }
}
