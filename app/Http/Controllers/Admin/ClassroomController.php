<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Classrooms\ChangeMeetingWeekday;
use App\Actions\Classrooms\CreateClassroom;
use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClassroomRequest;
use App\Http\Resources\ClassroomResource;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\User;
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
        $classroom = $create->handle($request->validated(), Church::primaryOrCreate());

        $this->toast("Classe \"{$classroom->name}\" criada.");

        return to_route('admin.classrooms.show', $classroom);
    }

    public function edit(Classroom $classroom): Response
    {
        Gate::authorize('update', $classroom);

        return Inertia::render('admin/classrooms/form', [
            'classroom' => [...ClassroomResource::make($classroom)->resolve(), 'position' => $classroom->position],
            'teachers' => $classroom->teachers()->orderBy('name')->get(['users.id', 'users.name', 'users.email'])
                ->map(fn (User $teacher) => ['id' => $teacher->id, 'name' => $teacher->name, 'email' => $teacher->email]),
        ]);
    }

    public function update(ClassroomRequest $request, Classroom $classroom, ChangeMeetingWeekday $changeWeekday): RedirectResponse
    {
        $data = $request->validated();
        $weekday = isset($data['meeting_weekday']) ? Weekday::from((int) $data['meeting_weekday']) : null;
        unset($data['meeting_weekday']);

        $classroom->update($data);

        $moved = $weekday !== null && $weekday !== $classroom->meeting_weekday
            ? $changeWeekday->handle($classroom, $weekday)
            : 0;

        $this->toast($moved > 0 ? "Classe atualizada. Encontros remarcados para {$weekday->label()}." : 'Classe atualizada.');

        return to_route('admin.classrooms.show', $classroom);
    }
}
