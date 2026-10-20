<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dados da igreja atual (nome e fuso), para quem a administra.
 */
class ChurchSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $church = $this->church($request);

        return Inertia::render('admin/church', [
            // Não "church": essa prop é a compartilhada pelo cabeçalho (seletor de igreja).
            'settings' => $church->only(['id', 'name', 'timezone']),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $church = $this->church($request);

        $church->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'string', 'timezone:all'],
        ], [
            'timezone.timezone' => 'Escolha um fuso horário da lista.',
        ]));

        $this->toast('Dados da igreja salvos.');

        return to_route('admin.church.edit');
    }

    private function church(Request $request): Church
    {
        /** @var User $user */
        $user = $request->user();
        $church = $user->currentChurch();

        // Só quem administra a igreja atual (a biblioteca oficial nunca é a atual).
        abort_unless($church !== null && $user->churchChoices()->contains($church), 403);

        return $church;
    }
}
