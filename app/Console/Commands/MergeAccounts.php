<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Junta duas contas da mesma pessoa (ex.: aluno do link que saiu do app e
 * criou outra conta). Fica a conta com o histórico da classe; o e-mail, a
 * senha e o que a outra tinha (classes, leituras, aparelhos) passam para ela.
 * A outra conta não é apagada: fica vazia e sem login.
 */
#[Signature('ebd:merge-accounts {keep : id da conta que fica (com o histórico)} {duplicate : id da conta que fica vazia} {--dry-run : só mostra o que seria feito}')]
#[Description('Junta duas contas da mesma pessoa numa só, sem apagar nenhuma')]
class MergeAccounts extends Command
{
    /**
     * Tabelas com user_id e as colunas que, junto com ele, não podem repetir.
     * O que repetir fica na conta vazia.
     *
     * @var array<string, list<string>>
     */
    private const OWNED = [
        'classroom_user' => ['classroom_id'],
        'attendances' => ['class_meeting_id'],
        'reading_checkins' => ['lesson_id', 'weekday'],
        'lesson_notes' => ['lesson_id'],
        'push_subscriptions' => [],
    ];

    public function handle(): int
    {
        $keep = User::query()->find((int) $this->argument('keep'));
        $duplicate = User::query()->find((int) $this->argument('duplicate'));

        if ($keep === null || $duplicate === null || $keep->is($duplicate)) {
            $this->components->error('Informe dois ids de contas diferentes e existentes.');

            return self::FAILURE;
        }

        if ($keep->canAccessAdmin() || $duplicate->canAccessAdmin()) {
            $this->components->error('Contas de professores ou administradores não são juntadas por aqui.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Fica', "#{$keep->id} {$keep->name} ".($keep->email ?? '(sem e-mail)'));
        $this->components->twoColumnDetail('Fica vazia', "#{$duplicate->id} {$duplicate->name} ".($duplicate->email ?? '(sem e-mail)'));

        foreach (array_keys(self::OWNED) as $table) {
            $this->components->twoColumnDetail("  {$table}", (string) DB::table($table)->where('user_id', $duplicate->id)->count());
        }

        // Só um login sobrevive: com duas senhas, o e-mail da conta vazia deixaria de funcionar.
        if ($keep->getRawOriginal('password') !== null && $duplicate->getRawOriginal('password') !== null) {
            $this->components->error('As duas contas têm senha. Combine com a pessoa qual login fica e bloqueie a outra antes de juntar.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->components->info('Nada foi alterado (--dry-run).');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($keep, $duplicate) {
            foreach (self::OWNED as $table => $columns) {
                DB::table($table)
                    ->where('user_id', $duplicate->id)
                    ->whereNotExists(function ($exists) use ($table, $columns, $keep) {
                        $exists->from("{$table} as k")->where('k.user_id', $keep->id);

                        foreach ($columns as $column) {
                            $exists->whereColumn("k.{$column}", "{$table}.{$column}");
                        }

                        if ($columns === []) {
                            $exists->whereRaw('false');
                        }
                    })
                    ->update(['user_id' => $keep->id]);
            }

            // O login da pessoa passa a ser o que ela mesma criou.
            $email = $duplicate->email;
            $passwordHash = $duplicate->getRawOriginal('password');

            // A conta vazia perde o login e os aparelhos conectados.
            DB::table('users')->where('id', $duplicate->id)->update([
                'email' => null,
                'password' => null,
                'remember_token' => Str::random(60),
            ]);
            DB::table('sessions')->where('user_id', $duplicate->id)->delete();

            $updates = [
                'name' => $duplicate->name,
                'email' => $keep->email ?? $email,
                'phone' => $keep->phone ?? $duplicate->phone,
                'birth_date' => $keep->birth_date?->toDateString() ?? $duplicate->birth_date?->toDateString(),
                'gender' => $keep->gender->value ?? $duplicate->gender?->value,
            ];

            // Já é o hash: vai direto, sem passar pelo cast "hashed" de novo.
            if ($keep->getRawOriginal('password') === null && $passwordHash !== null) {
                $updates['password'] = $passwordHash;
            }

            DB::table('users')->where('id', $keep->id)->update($updates);

            // Com senha, o link pessoal deixa de valer.
            if (isset($updates['password'])) {
                $keep->accessLinks()->active()->update(['revoked_at' => now()]);
            }
        });

        $keep->refresh();

        $this->components->info("Contas juntadas. {$keep->name} entra com ".($keep->email ?? 'o link pessoal').'.');

        return self::SUCCESS;
    }
}
