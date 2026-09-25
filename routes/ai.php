<?php

use App\Mcp\Servers\EbdServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

/*
| Servidor MCP para agentes de IA (docs/arquitetura.md > Agentes (MCP)).
|
| O login é OAuth (Passport): o aplicativo do agente se registra sozinho em
| /oauth/register, a pessoa entra com a conta dela e autoriza na tela de
| consentimento, e o token passa a valer só para quem acessa a gestão.
| Este arquivo é carregado pelo pacote laravel/mcp, fora do grupo "web".
*/

Route::middleware('throttle:30,1')->group(fn () => Mcp::oauthRoutes());

Mcp::web('/mcp', EbdServer::class)
    ->middleware(['auth:api', 'can:access-admin', 'throttle:mcp']);
