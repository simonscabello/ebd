<?php

use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Em produção (Railway) a aplicação só é alcançável através do proxy da
        // plataforma, que termina o HTTPS e envia X-Forwarded-*. Confiar nele faz o
        // Laravel reconhecer HTTPS, host e IP real do visitante.
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // O service worker renova a inscrição de push sem o token CSRF da página.
        $middleware->validateCsrfTokens(except: ['notificacoes/renovacao']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
            EnsureProfileIsComplete::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // O token do link pessoal nunca volta para a sessão em caso de erro de validação.
        $exceptions->dontFlash(['token', 'current_password', 'password', 'password_confirmation']);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Páginas de erro com a cara do app (e em português). Com debug ligado,
        // o Laravel continua mostrando o detalhe do erro.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson() || $request->is('api/*', 'mcp*', 'oauth/*')) {
                return $response;
            }

            // Sessão expirada (token CSRF vencido): volta e explica, sem tela de erro.
            if ($status === 419) {
                Inertia::flash('toast', [
                    'type' => 'warning',
                    'message' => 'Sua sessão expirou. Tente de novo.',
                ]);

                return back();
            }

            if (config('app.debug') || ! in_array($status, [403, 404, 500, 503], true)) {
                return $response;
            }

            return Inertia::render('error', [
                'status' => $status,
                // Um endereço que não casa com nenhuma rota não passa pelo
                // middleware do Inertia: o nome da igreja vai junto aqui.
                'church' => ['name' => config('ebd.church_name')],
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
