<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ClassroomRole;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Página "Perfil": foto, participação nas classes e atalhos da conta.
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/index', [
            'classrooms' => $user->classrooms()
                ->ordered()
                ->get(['classrooms.id', 'classrooms.name', 'classrooms.slug'])
                ->map(function (Classroom $classroom) {
                    /** @var ClassroomRole $role */
                    $role = $classroom->getRelationValue('pivot')->role;

                    return [
                        'id' => $classroom->id,
                        'name' => $classroom->name,
                        'slug' => $classroom->slug,
                        'role' => $role->value,
                        'role_label' => $role === ClassroomRole::Teacher ? 'Professor' : 'Aluno',
                    ];
                }),
        ]);
    }
}
