<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Domínio próprio (EBD_CANONICAL_HOST): os endereços antigos
 * (EBD_REDIRECT_HOSTS, ex.: ebd.up.railway.app e o www) levam para ele no
 * mesmo caminho, para links pessoais e de lições já enviados continuarem
 * valendo. GET vira 301; os outros métodos, 308 (mantém o método e o corpo).
 * O health check (/up) nunca é redirecionado. Sem domínio configurado, nada
 * acontece.
 */
class RedirectToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = config('ebd.canonical_host');
        $host = strtolower($request->getHost());

        if (! $canonical || $request->is('up') || ! in_array($host, (array) config('ebd.redirect_hosts'), true)) {
            return $next($request);
        }

        $status = $request->isMethod('GET') || $request->isMethod('HEAD') ? 301 : 308;

        return redirect()->away('https://'.$canonical.$request->getRequestUri(), $status);
    }
}
