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
 * Sem domínio configurado, nada acontece.
 *
 * Ficam de fora o que não é navegação de gente e quebraria com o redirect:
 * - /up: o health check;
 * - /mcp, /.well-known/*, /oauth/token e /oauth/register: clientes MCP que
 *   guardaram o endereço antigo. Um redirect entre hosts tira o Authorization,
 *   e o "resource" do OAuth (montado com o host da requisição) deixa de bater.
 *   A tela de autorização (GET /oauth/authorize) continua indo para o domínio
 *   novo: é a pessoa no navegador, e o login só funciona lá;
 * - /notificacoes/* e /sw.js: o service worker antigo renova a inscrição de
 *   push no próprio host (um POST redirecionado vira CORS e falha) e o script
 *   do service worker não pode ser redirecionado.
 */
class RedirectToCanonicalHost
{
    /** @var list<string> */
    private const EXEMPT_PATHS = [
        'up',
        'mcp',
        'mcp/*',
        '.well-known/*',
        'oauth/token',
        'oauth/register',
        'notificacoes/*',
        'sw.js',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $canonical = self::canonicalHost();
        $host = strtolower($request->getHost());

        if (
            ! $canonical
            || $host === $canonical
            || $request->is(...self::EXEMPT_PATHS)
            || ! in_array($host, (array) config('ebd.redirect_hosts'), true)
        ) {
            return $next($request);
        }

        $status = $request->isMethod('GET') || $request->isMethod('HEAD') ? 301 : 308;

        return redirect()->away('https://'.$canonical.$request->getRequestUri(), $status);
    }

    /**
     * O domínio configurado, aceito com ou sem esquema e barra no fim
     * ("https://usedidache.com.br/" vira "usedidache.com.br").
     */
    private static function canonicalHost(): ?string
    {
        $canonical = strtolower(trim((string) config('ebd.canonical_host')));
        $canonical = (string) preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $canonical);
        $canonical = rtrim($canonical, '/');

        return $canonical !== '' ? $canonical : null;
    }
}
