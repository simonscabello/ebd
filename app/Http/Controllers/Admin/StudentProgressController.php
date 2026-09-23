<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use App\Models\User;
use App\Queries\StudentProgressQuery;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Progresso de um aluno visto pelo professor. Anotações pessoais nunca entram.
 */
class StudentProgressController extends Controller
{
    public function __invoke(Classroom $classroom, User $user, StudentProgressQuery $progress): Response
    {
        Gate::authorize('viewStudentProgress', [$classroom, $user]);

        $link = $user->accessLinks()->active()->first();

        return Inertia::render('admin/classrooms/student', [
            'classroom' => ClassroomResource::make($classroom),
            'student' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_managed' => $user->isManaged(),
                'access_link' => $link ? [
                    'use_count' => $link->use_count,
                    'last_used_at' => $link->last_used_at?->toIso8601String(),
                ] : null,
            ],
            'progress' => $progress->for($user, $classroom),
        ]);
    }
}
