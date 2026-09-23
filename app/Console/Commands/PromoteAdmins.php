<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Bootstrap seguro do primeiro administrador em produção.
 *
 * A pessoa cria a conta normalmente pelo site e depois o e-mail dela é
 * informado aqui (argumento) ou em EBD_ADMIN_EMAILS. O comando só PROMOVE
 * contas já existentes: não cria usuários nem define senhas. É idempotente e
 * roda no pre-deploy, então pode ficar configurado sem efeito colateral.
 */
#[Signature('ebd:promote-admins {emails?* : E-mails das contas a promover (padrão: EBD_ADMIN_EMAILS)}')]
#[Description('Promove contas existentes a administrador da EBD')]
class PromoteAdmins extends Command
{
    public function handle(): int
    {
        /** @var list<string> $emails */
        $emails = $this->argument('emails') ?: (array) config('ebd.admin_emails');

        $emails = array_values(array_unique(array_filter(array_map(
            fn (string $email) => mb_strtolower(trim($email)),
            $emails,
        ))));

        if ($emails === []) {
            $this->components->info('Nenhum e-mail de administrador configurado.');

            return self::SUCCESS;
        }

        foreach ($emails as $email) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $this->components->warn("Conta não encontrada para {$email}: crie a conta pelo site e rode novamente.");

                continue;
            }

            if ($user->isAdmin()) {
                $this->components->info("{$email} já é administrador(a).");

                continue;
            }

            $user->forceFill(['is_admin' => true])->save();
            $this->components->info("{$email} agora é administrador(a).");
        }

        return self::SUCCESS;
    }
}
