<?php

namespace App\Http\Controllers;

use App\Actions\Access\LoginWithAccessLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Entrada pelo link pessoal (/entrar#token).
 *
 * O GET só entrega a página: o token está no fragmento e nunca chega ao
 * servidor nessa requisição. A página lê o fragmento, apaga-o da barra de
 * endereços e envia o token por POST. Assim, o preview do WhatsApp (que só faz
 * GET) não consegue entrar nem consumir o link.
 */
class AccessLinkController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('auth/access-link', [
            'currentUser' => $request->user()?->name,
        ]);
    }

    public function store(Request $request, LoginWithAccessLink $login): RedirectResponse
    {
        $token = $request->input('token');

        if (! is_string($token) || preg_match('/^[A-Za-z0-9]{48}$/', $token) !== 1) {
            throw ValidationException::withMessages(['token' => LoginWithAccessLink::INVALID]);
        }

        $user = $login->handle($token);

        $this->toast('Olá, '.strtok($user->name, ' ').'! Este aparelho vai lembrar de você.');

        return redirect()->route('my-week');
    }
}
