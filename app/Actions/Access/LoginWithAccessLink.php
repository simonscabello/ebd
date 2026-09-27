<?php

namespace App\Actions\Access;

use App\Models\AccessLink;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Entrada pelo link pessoal. Qualquer falha devolve a mesma mensagem genérica,
 * sem revelar se o link existiu ou de quem era.
 *
 * Em conta que já tem senha, o link é de recuperação: a senha antiga é apagada
 * e a pessoa cria outra em "Completar cadastro" antes de usar o app.
 */
class LoginWithAccessLink
{
    public const INVALID = 'Este link não é mais válido. Se você já criou sua senha, entre com e-mail e senha; se não, peça um novo link ao seu professor.';

    public function handle(string $token): User
    {
        $link = AccessLink::query()->where('token_hash', AccessLink::hashToken($token))->with('user')->first();
        $user = $link?->user;

        // Defesa em profundidade: um link nunca autentica professores ou administradores.
        if ($link === null || $user === null || ! $link->isUsable() || $user->isAdmin() || $user->canAccessAdmin()) {
            throw ValidationException::withMessages(['token' => self::INVALID]);
        }

        if (Auth::check() && Auth::id() !== $user->id) {
            Auth::guard('web')->logout();
        }

        if (! $user->isManaged()) {
            $user->forceFill(['password' => null])->save();
        }

        $guard = Auth::guard('web');

        if (method_exists($guard, 'setRememberDuration')) {
            $guard->setRememberDuration((int) config('ebd.access_links.remember_days') * 24 * 60);
        }

        $guard->login($user, remember: true);
        session()->regenerate();

        DB::table('access_links')->where('id', $link->id)->update([
            'use_count' => DB::raw('use_count + 1'),
            'last_used_at' => now(),
        ]);

        return $user;
    }
}
