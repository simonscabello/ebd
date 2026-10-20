<?php

namespace App\Actions\Access;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Bloquear acesso": revoga o link e encerra as sessões e o "lembrar de mim"
 * da pessoa (ex.: link repassado para outra pessoa, celular perdido).
 */
class RevokeStudentAccess
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->accessLinks()->active()->update(['revoked_at' => now()]);

            // Invalida o cookie "lembrar de mim".
            $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

            if (config('session.driver') === 'database') {
                DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
        });
    }

    /**
     * Para quem passa a gerenciar (professor, coordenador): os links deixam de
     * valer e, se a pessoa já teve link, as sessões abertas por ele caem também.
     * Quem nunca teve link (ou é quem está nomeando) não é deslogado à toa.
     */
    public function revokeForManager(User $user, ?User $actor = null): void
    {
        if ($user->isNot($actor) && $user->accessLinks()->exists()) {
            $this->handle($user);

            return;
        }

        $user->accessLinks()->active()->update(['revoked_at' => now()]);
    }
}
