<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Páginas fora do Inertia (a tela de consentimento do OAuth, em Blade) não
 * podem ser abertas por uma navegação do Inertia: depois do login, o Fortify
 * devolve a pessoa para /oauth/authorize e o Inertia mostraria o HTML num
 * modal por cima do login. Aqui a navegação vira um carregamento de página
 * inteira (409 + X-Inertia-Location).
 */
class RequireFullPageVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->header('X-Inertia')) {
            return Inertia::location($request->fullUrl());
        }

        return $next($request);
    }
}
