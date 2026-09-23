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
 */
class LoginWithAccessLink
{
    public const INVALID = 'Este link não é mais válido. Peça um novo ao seu professor.';

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
