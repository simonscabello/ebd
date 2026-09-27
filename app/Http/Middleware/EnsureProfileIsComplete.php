<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aluno com cadastro incompleto (sem senha, e-mail, WhatsApp, nascimento ou
 * gênero) vai para "Completar cadastro" antes de usar o app. Professores e
 * administradores não passam por aqui.
 */
class EnsureProfileIsComplete
{
    /**
     * Rotas liberadas enquanto o cadastro não termina.
     *
     * @var list<string>
     */
    private const ALLOWED = ['onboarding.*', 'logout', 'access-link.*', 'push.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User
            || ! config('ebd.onboarding.required')
            || $request->routeIs(...self::ALLOWED)
            || ! $user->needsProfileCompletion()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Complete seu cadastro para continuar.'], Response::HTTP_CONFLICT);
        }

        // guest() guarda a página pedida para voltar a ela depois do cadastro.
        return redirect()->guest(route('onboarding.show'));
    }
}
