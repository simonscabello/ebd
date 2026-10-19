<?php

namespace App\Http\Controllers;

use App\Actions\Churches\CreateChurchWithAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Criar minha igreja" para quem já tem conta: o mesmo login passa a
 * administrar a igreja nova.
 */
class CreateChurchController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/register', ['churchOnly' => true]);
    }

    public function store(Request $request, CreateChurchWithAdmin $createChurch): RedirectResponse
    {
        $data = $request->validate([
            'church_name' => ['required', 'string', 'max:120'],
            'program_name' => ['nullable', 'string', 'max:100'],
        ], [], ['church_name' => 'nome da igreja ou grupo', 'program_name' => 'nome do programa']);

        $church = $createChurch->handle($request->user(), $data['church_name'], $data['program_name'] ?? null);

        $this->toast("{$church->name} foi criada. Comece pela primeira turma.");

        return redirect('/admin');
    }
}
