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
}
