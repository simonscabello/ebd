<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Troca a igreja atual de quem administra mais de uma: o cabeçalho, o painel,
 * as listas e a criação de classes passam a ser dela.
 */
class CurrentChurchController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $choices = $user->churchChoices();

        $data = $request->validate([
            'church_id' => ['required', 'integer', Rule::in($choices->modelKeys())],
        ], [
            'church_id.in' => 'Escolha uma das igrejas que você administra.',
        ]);

        $user->forceFill(['current_church_id' => $data['church_id']])->save();

        $this->toast('Agora você está em '.$choices->firstWhere('id', $data['church_id'])?->name.'.');

        return to_route('admin.dashboard');
    }
}
