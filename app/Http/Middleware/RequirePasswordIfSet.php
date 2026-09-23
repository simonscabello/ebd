<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confirmação de senha antes de áreas sensíveis, exceto para quem ainda não
 * tem senha (aluno que entra pelo link pessoal e vai criar a primeira).
 */
class RequirePasswordIfSet extends RequirePassword
{
    /**
     * @param  Request  $request
     */
    public function handle($request, Closure $next, $redirectToRoute = null, $passwordTimeoutSeconds = null): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isManaged()) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute, $passwordTimeoutSeconds);
    }
}
