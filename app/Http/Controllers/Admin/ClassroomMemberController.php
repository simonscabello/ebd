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
use Illuminate\Support\Facades\Gate;

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

        $classroom->members()->detach($user->id);
        $user->flushClassroomRoles();

        // O link desta classe deixa de valer. Sem senha e sem nenhuma classe,
        // a conta não tem mais o que fazer no app: encerra os aparelhos também.
        $user->accessLinks()->active()->where('classroom_id', $classroom->id)->update(['revoked_at' => now()]);

        if ($user->isManaged() && $user->memberClassroomIds() === []) {
            $revoke->handle($user);
        }

        $this->toast("{$user->name} foi removido(a) da classe.");

        // Professor sai pela edição da classe; aluno, pela ficha, que deixa de
        // existir: nesse caso volta para a lista de Alunos.
        return $wasTeacher ? back() : to_route('admin.classrooms.students.index', $classroom);
    }
}
