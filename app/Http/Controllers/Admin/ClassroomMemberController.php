<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Classrooms\AddClassroomMember;
use App\Enums\ClassroomRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClassroomMemberRequest;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomMemberController extends Controller
{
    public function index(Request $request, Classroom $classroom): Response
    {
        Gate::authorize('manageMembers', $classroom);

        $members = ClassroomMember::query()
            ->whereBelongsTo($classroom)
            ->with(['user.accessLinks' => fn ($q) => $q->active()])
            ->get()
            ->sortBy(fn (ClassroomMember $member) => $member->user->name)
            ->values()
            ->map(fn (ClassroomMember $member) => [
                'id' => $member->user->id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'phone' => $member->user->phone,
                'role' => $member->role->value,
                'role_label' => $member->role->label(),
                'is_managed' => $member->user->isManaged(),
                'access_link' => ($link = $member->user->accessLinks->first()) ? [
                    'created_at' => $link->created_at->toIso8601String(),
                    'use_count' => $link->use_count,
                    'last_used_at' => $link->last_used_at?->toIso8601String(),
                    'expires_at' => $link->expires_at?->toIso8601String(),
                ] : null,
            ]);

        return Inertia::render('admin/classrooms/members', [
            'classroom' => ClassroomResource::make($classroom),
            'members' => $members,
            'canAssignTeachers' => $request->user()->can('assignTeachers', $classroom),
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    public function store(ClassroomMemberRequest $request, Classroom $classroom, AddClassroomMember $add): RedirectResponse
    {
        $role = ClassroomRole::from($request->validated('role'));
        $user = $add->handle($classroom, $request->validated('email'), $role);

        $this->toast("{$user->name} agora é {$role->label()} da classe.");

        return back();
    }

    public function destroy(Request $request, Classroom $classroom, User $user): RedirectResponse
    {
        Gate::authorize('manageMembers', $classroom);

        // Professores só podem ser removidos pela administração.
        if ($user->isTeacherOf($classroom)) {
            Gate::authorize('assignTeachers', $classroom);
        }

        $classroom->members()->detach($user->id);

        $this->toast("{$user->name} foi removido(a) da classe.");

        return back();
    }
}
