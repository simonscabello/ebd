<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Access\IssueAccessLink;
use App\Actions\Access\RevokeStudentAccess;
use App\Actions\Classrooms\CreateManagedStudent;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Alunos sem e-mail/senha e seus links pessoais de acesso.
 */
class StudentAccessController extends Controller
{
    public function store(Request $request, Classroom $classroom, CreateManagedStudent $create): RedirectResponse
    {
        Gate::authorize('manageMembers', $classroom);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 ()+.-]{8,30}$/'],
        ], ['phone.regex' => 'Use só números, com DDD (e DDI, se for de fora do Brasil).'], ['name' => 'nome', 'phone' => 'telefone']);

        $result = $create->handle($classroom, $data['name'], $data['phone'] ?? null, $request->user());

        $this->flashLink($result['user'], $result['url']);
        $this->toast("{$result['user']->name} foi adicionado(a). Envie o link de acesso.");

        return back();
    }

    public function issue(Request $request, Classroom $classroom, User $user, IssueAccessLink $issue): RedirectResponse
    {
        Gate::authorize('manageMembers', $classroom);
        abort_unless($user->isMemberOf($classroom), 404);

        $url = $issue->handle($user, $classroom, $request->user());

        $this->flashLink($user, $url);
        $this->toast('Novo link gerado. O link anterior deixou de funcionar.');

        return back();
    }

    public function revoke(Classroom $classroom, User $user, RevokeStudentAccess $revoke): RedirectResponse
    {
        Gate::authorize('manageMembers', $classroom);
        abort_unless($user->isMemberOf($classroom) && ! $user->isTeacherOf($classroom), 404);

        $revoke->handle($user);

        $this->toast("Acesso de {$user->name} bloqueado em todos os aparelhos.");

        return back();
    }

    /**
     * O link em claro só existe nesta resposta: não é guardado em lugar nenhum.
     */
    private function flashLink(User $user, string $url): void
    {
        Inertia::flash('accessLink', [
            'user_id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'url' => $url,
            'message' => "Olá, {$user->name}! Este é o seu link pessoal da EBD. Toque para entrar (não compartilhe):\n{$url}",
        ]);
    }
}
