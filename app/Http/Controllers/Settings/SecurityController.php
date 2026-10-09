<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/security', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => $request->password,
        ]);

        // Com senha própria, o link pessoal deixa de valer.
        $user->accessLinks()->active()->update(['revoked_at' => now()]);

        // O cookie "lembrar de mim" guarda um resumo da senha: refaz o login
        // para este aparelho continuar lembrando da pessoa.
        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
