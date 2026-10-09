<?php

namespace App\Http\Controllers;

use App\Actions\Access\CompleteStudentProfile;
use App\Enums\Gender;
use App\Http\Requests\CompleteProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Completar cadastro": tela obrigatória para o aluno que ainda não tem
 * e-mail, senha, WhatsApp, nascimento ou gênero (ver EnsureProfileIsComplete).
 */
class CompleteProfileController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->needsProfileCompletion()) {
            return redirect()->route('home');
        }

        return Inertia::render('auth/complete-profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'birth_date' => $user->birth_date?->toDateString(),
                'gender' => $user->gender?->value,
            ],
            'needsPassword' => $user->isManaged(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'genders' => Gender::options(),
        ]);
    }

    public function store(CompleteProfileRequest $request, CompleteStudentProfile $complete): RedirectResponse
    {
        $complete->handle($request->user(), $request->profile());

        $this->toast('Cadastro completo! Agora você entra com seu e-mail e senha.');

        return redirect()->intended(route('home'));
    }
}
