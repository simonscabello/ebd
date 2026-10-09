<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Access\IssueAccessLink;
use App\Actions\Access\RevokeStudentAccess;
use App\Actions\Classrooms\CreateManagedStudent;
use App\Actions\Classrooms\FindSimilarStudents;
use App\Concerns\StudentValidationRules;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Alunos sem e-mail/senha e seus links pessoais de acesso.
 */
class StudentAccessController extends Controller
{
    use StudentValidationRules;

    public function store(Request $request, Classroom $classroom, CreateManagedStudent $create, FindSimilarStudents $similar): RedirectResponse
    {
        Gate::authorize('manageMembers', $classroom);

        $data = $request->validate(
            [...$this->studentRules(), 'confirm_duplicate' => ['nullable', 'boolean']],
            $this->studentMessages(),
            $this->studentAttributes(),
        );

        // Nome parecido com o de alguém da classe: confirma antes de duplicar.
        if (! ($data['confirm_duplicate'] ?? false)) {
            $matches = $similar->in($classroom, $data['name']);

            if ($matches->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'duplicate' => 'Já existe aluno com nome parecido nesta classe: '.$matches->pluck('name')->implode(', ').'. Se for outra pessoa, adicione mesmo assim.',
                ]);
            }
        }

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
        $this->toast('Novo link de acesso gerado. O anterior deixou de funcionar.');

        return back();
    }

    public function revoke(Classroom $classroom, User $user, RevokeStudentAccess $revoke): RedirectResponse
    {
        Gate::authorize('manageMembers', $classroom);
        abort_unless($user->isMemberOf($classroom) && ! $user->isTeacherOf($classroom), 404);
        abort_if($user->isAdmin() || $user->canAccessAdmin(), 403, 'Professores e administradores não usam link de acesso.');

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
            'message' => "Olá, {$user->name}! Este é o seu link de acesso à EBD. Toque para entrar (não compartilhe):\n{$url}",
        ]);
    }
}
