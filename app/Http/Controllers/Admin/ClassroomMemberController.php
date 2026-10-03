<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Access\RevokeStudentAccess;
use App\Actions\Classrooms\AddClassroomMember;
use App\Enums\ClassroomRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClassroomMemberRequest;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

class ClassroomMemberController extends Controller
{
    public function store(ClassroomMemberRequest $request, Classroom $classroom, AddClassroomMember $add): RedirectResponse
    {
        $role = ClassroomRole::from($request->validated('role'));
        $user = $add->handle($classroom, $request->validated('email'), $role, $request->user());

        $this->toast("{$user->name} agora é {$role->label()} da classe.");

        return back();
    }

    public function destroy(Request $request, Classroom $classroom, User $user, RevokeStudentAccess $revoke): RedirectResponse
    {
        Gate::authorize('manageMembers', $classroom);

        // Professores só podem ser removidos pela administração.
        $wasTeacher = $user->isTeacherOf($classroom);

        if ($wasTeacher) {
            Gate::authorize('assignTeachers', $classroom);
        }

        // Guardados para o "Desfazer": o papel e a data de entrada, que conta
        // na frequência (ver Enrollment).
        $membership = DB::table('classroom_user')
            ->where('classroom_id', $classroom->id)
            ->where('user_id', $user->id)
            ->first(['role', 'created_at']);

        $classroom->members()->detach($user->id);
        $user->flushClassroomRoles();

        // O link desta classe deixa de valer. Sem senha e sem nenhuma classe,
        // a conta não tem mais o que fazer no app: encerra os aparelhos também.
        $user->accessLinks()->active()->where('classroom_id', $classroom->id)->update(['revoked_at' => now()]);

        if ($user->isManaged() && $user->memberClassroomIds() === []) {
            $revoke->handle($user);
        }

        $this->toast("{$user->name} foi removido(a) da classe.", action: $membership ? [
            'label' => 'Desfazer',
            'url' => URL::temporarySignedRoute('admin.classrooms.members.restore', now()->addMinutes(2), [
                'classroom' => $classroom,
                'user' => $user,
                'role' => $membership->role,
                'since' => Carbon::parse($membership->created_at)->getTimestamp(),
            ]),
        ] : null);

        // Professor sai pela edição da classe; aluno, pela ficha, que deixa de
        // existir: nesse caso volta para a lista de Alunos.
        return $wasTeacher ? back() : to_route('admin.classrooms.students.index', $classroom);
    }

    /**
     * "Desfazer" do toast de remoção: devolve a pessoa à classe com o papel e
     * a data de entrada de antes. O link assinado vale por 2 minutos. Links
     * de acesso revogados na remoção continuam revogados.
     */
    public function restore(Request $request, Classroom $classroom, User $user): RedirectResponse
    {
        Gate::authorize('manageMembers', $classroom);

        $role = ClassroomRole::from((string) $request->query('role'));

        if ($role === ClassroomRole::Teacher) {
            Gate::authorize('assignTeachers', $classroom);
        }

        if ($user->isMemberOf($classroom->id)) {
            $this->toast("{$user->name} já está na classe.", 'info');

            return back();
        }

        $classroom->members()->attach($user->id, [
            'role' => $role->value,
            'created_at' => Carbon::createFromTimestamp((int) $request->query('since')),
            'updated_at' => now(),
        ]);
        $user->flushClassroomRoles();

        $this->toast($user->isManaged()
            ? "{$user->name} voltou para a classe. Envie um novo link de acesso."
            : "{$user->name} voltou para a classe.");

        return $role === ClassroomRole::Teacher
            ? back()
            : to_route('admin.classrooms.students.show', [$classroom, $user]);
    }
}
