<?php

namespace App\Console\Commands;

use App\Models\Church;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Bootstrap seguro do DONO DO SISTEMA em produção (is_admin).
 *
 * is_admin não é "administrador de igreja" (isso é church_user, criado em
 * "Criar minha igreja"): é quem cuida da plataforma inteira, com o gate
 * own-platform (painel /dono/igrejas, IA por igreja e biblioteca oficial).
 * Só o Simon deve estar em EBD_ADMIN_EMAILS.
 *
 * A pessoa cria a conta normalmente pelo site e depois o e-mail dela é
 * informado aqui (argumento) ou em EBD_ADMIN_EMAILS. O comando só PROMOVE
 * contas já existentes: não cria usuários nem define senhas. É idempotente e
 * roda no pre-deploy, então pode ficar configurado sem efeito colateral.
 */
#[Signature('ebd:promote-admins {emails?* : E-mails das contas a promover a dono do sistema (padrão: EBD_ADMIN_EMAILS)}')]
#[Description('Promove contas existentes a dono do sistema (is_admin: painel /dono e biblioteca oficial de todas as igrejas)')]
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
            $this->components->info('Nenhum e-mail de dono do sistema configurado.');

            return self::SUCCESS;
        }

        // O dono do sistema também administra a igreja dele (a mais antiga).
        $church = Church::primary();

        foreach ($emails as $email) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $this->components->warn("Conta não encontrada para {$email}: crie a conta pelo site e rode novamente.");

                continue;
            }

            $church?->admins()->syncWithoutDetaching([$user->id]);
            // E a biblioteca oficial, onde ficam os estudos do dono.
            Church::query()->where('is_official', true)->first()?->admins()->syncWithoutDetaching([$user->id]);

            if ($user->isAdmin()) {
                $this->components->info("{$email} já é dono(a) do sistema.");

                continue;
            }

            $user->forceFill(['is_admin' => true])->save();
            $this->components->info("{$email} agora é dono(a) do sistema.");
        }

        return self::SUCCESS;
    }
}
