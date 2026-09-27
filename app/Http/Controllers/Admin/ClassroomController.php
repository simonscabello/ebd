<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Classrooms\CreateClassroom;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClassroomRequest;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Classroom::class);

        $user = $request->user();
        $manageable = $user->manageableClassroomIds();

        $classrooms = Classroom::query()
            ->when($manageable !== null, fn ($query) => $query->whereIn('id', $manageable))
            ->ordered()
            ->withCount(['students', 'lessons', 'series'])
            ->get();

        // Professor de uma classe só: a lista seria um passo a mais.
        if ($manageable !== null && $classrooms->count() === 1) {
            return to_route('admin.classrooms.show', $classrooms->first());
        }

        return Inertia::render('admin/classrooms/index', [
            'classrooms' => $classrooms->map(fn (Classroom $classroom) => [
                ...ClassroomResource::make($classroom)->resolve(),
                'students_count' => (int) $classroom->getAttribute('students_count'),
                'lessons_count' => (int) $classroom->getAttribute('lessons_count'),
                'series_count' => (int) $classroom->getAttribute('series_count'),
            ]),
            'canCreate' => $user->can('create', Classroom::class),
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

        return to_route('admin.classrooms.show', $classroom);
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

        return to_route('admin.classrooms.show', $classroom);
    }
}
