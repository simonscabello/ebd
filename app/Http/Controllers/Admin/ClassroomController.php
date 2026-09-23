<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Classrooms\CreateClassroom;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClassroomRequest;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Classroom::class);

        $classrooms = Classroom::query()->ordered()->withCount(['members', 'lessons', 'series'])->get();

        return Inertia::render('admin/classrooms/index', [
            'classrooms' => $classrooms->map(fn (Classroom $classroom) => [
                ...ClassroomResource::make($classroom)->resolve(),
                'members_count' => $classroom->members_count,
                'lessons_count' => $classroom->lessons_count,
                'series_count' => $classroom->series_count,
            ]),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Classroom::class);

        return Inertia::render('admin/classrooms/form', ['classroom' => null]);
    }

    public function store(ClassroomRequest $request, CreateClassroom $create): RedirectResponse
    {
        $classroom = $create->handle($request->validated());

        $this->toast("Classe \"{$classroom->name}\" criada.");

        return to_route('admin.classrooms.members.index', $classroom);
    }

    public function edit(Classroom $classroom): Response
    {
        Gate::authorize('update', $classroom);

        return Inertia::render('admin/classrooms/form', [
            'classroom' => [...ClassroomResource::make($classroom)->resolve(), 'position' => $classroom->position],
        ]);
    }

    public function update(ClassroomRequest $request, Classroom $classroom): RedirectResponse
    {
        $classroom->update($request->validated());

        $this->toast('Classe atualizada.');

        return to_route('admin.classrooms.index');
    }
}
