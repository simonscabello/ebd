<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Coordenadores de um programa: gerenciam todas as turmas dele. Nomeados por
 * quem nomeia professores.
 */
class ProgramCoordinatorController extends Controller
{
    public function store(Request $request, Program $program): RedirectResponse
    {
        Gate::authorize('appointCoordinators', $program);

        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']], [], ['email' => 'e-mail']);
        $user = User::query()->where('email', mb_strtolower(trim($data['email'])))->first();

        if ($user === null) {
            throw ValidationException::withMessages(['email' => 'Nenhuma conta encontrada com este e-mail.']);
        }

        $program->coordinators()->syncWithoutDetaching([$user->id]);

        // Como ao virar professor: quem gerencia entra com e-mail e senha, e um
        // link pessoal antigo não pode voltar a valer se a coordenação acabar.
        $user->accessLinks()->active()->update(['revoked_at' => now()]);
        $this->toast("{$user->name} agora coordena {$program->name}.");

        return back();
    }

    public function destroy(Program $program, User $user): RedirectResponse
    {
        Gate::authorize('appointCoordinators', $program);

        $program->coordinators()->detach($user->id);
        $this->toast("{$user->name} não coordena mais {$program->name}.");

        return back();
    }
}
