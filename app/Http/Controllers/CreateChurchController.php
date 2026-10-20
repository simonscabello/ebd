<?php

namespace App\Http\Controllers;

use App\Actions\Churches\CreateChurchWithAdmin;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        $user = $request->user();
        $limit = (int) config('ebd.max_churches_per_account');

        // Um teto por conta contra a criação em massa. A biblioteca oficial não
        // conta, e o dono do sistema não tem teto. A linha da pessoa fica
        // travada até o fim: dois envios juntos não passam pela última vaga.
        $church = DB::transaction(function () use ($user, $limit, $data, $createChurch) {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if (! $user->isAdmin() && $this->administeredCount($user) >= $limit) {
                throw ValidationException::withMessages([
                    'church_name' => "Cada conta pode administrar até {$limit} igrejas ou grupos. Para criar outra, peça a alguém da liderança que crie com a conta dele.",
                ]);
            }

            return $createChurch->handle($user, $data['church_name'], $data['program_name'] ?? null);
        });

        $this->toast("{$church->name} foi criada. Comece pela primeira turma.");

        return redirect('/admin');
    }

    private function administeredCount(User $user): int
    {
        return $user->administeredChurches()->where('is_official', false)->count();
    }
}
