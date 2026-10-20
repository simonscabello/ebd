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
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Classroom::class);

        $user = $request->user();
        $manageable = $user->listedClassroomIds();

        $classrooms = Classroom::query()
            ->whereIn('id', $manageable)
            ->ordered()
            ->with('program')
            ->withCount(['students', 'lessons', 'series'])
            ->get();

        // Professor de uma classe só: a lista seria um passo a mais. Quem
        // administra a igreja fica na lista, onde se cria turma.
        if (! $user->can('create', Classroom::class) && $classrooms->count() === 1) {
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

    public function create(Request $request): Response
    {
        Gate::authorize('create', Classroom::class);

        $church = $request->user()->currentChurch();

        return Inertia::render('admin/classrooms/form', [
            'classroom' => null,
            'programs' => $church === null ? [] : $this->programOptions($church),
            'defaultProgramId' => $church?->defaultProgram()->id,
        ]);
    }

    public function store(ClassroomRequest $request, CreateClassroom $create): RedirectResponse
    {
        // A igreja da pessoa (a biblioteca oficial nunca é).
        $church = $request->user()->currentChurch();
        abort_if($church === null || ! $request->user()->isChurchAdminOf($church), 403);
        $data = $request->validated();
        $program = isset($data['program_id']) ? $church->programs()->whereKey((int) $data['program_id'])->firstOrFail() : $church->defaultProgram();
        $classroom = $create->handle($data, $program);

        $this->toast("Classe \"{$classroom->name}\" criada.");

        return to_route('admin.classrooms.show', $classroom);
    }

    public function edit(Classroom $classroom): Response
    {
        Gate::authorize('update', $classroom);

        return Inertia::render('admin/classrooms/form', [
            'classroom' => [
                ...ClassroomResource::make($classroom)->resolve(),
                'position' => $classroom->position,
                'program_id' => $classroom->program_id,
            ],
            'programs' => $this->programOptions($classroom->church),
            'teachers' => $classroom->teachers()->orderBy('name')->get(['users.id', 'users.name', 'users.email'])
                ->map(fn (User $teacher) => ['id' => $teacher->id, 'name' => $teacher->name, 'email' => $teacher->email]),
        ]);
    }

    public function update(ClassroomRequest $request, Classroom $classroom, ChangeMeetingWeekday $changeWeekday): RedirectResponse
    {
        $data = $request->validated();
        $weekday = isset($data['meeting_weekday']) ? Weekday::from((int) $data['meeting_weekday']) : null;
        $programId = isset($data['program_id']) ? (int) $data['program_id'] : $classroom->program_id;
        unset($data['meeting_weekday'], $data['program_id']);

        // Nome, programa e dia juntos: ou salva tudo, ou nada.
        $moved = DB::transaction(function () use ($classroom, $data, $programId, $weekday, $changeWeekday) {
            $classroom->program_id = $programId;
            $classroom->update($data);

            return $weekday !== null && $weekday !== $classroom->meeting_weekday
                ? $changeWeekday->handle($classroom, $weekday)
                : 0;
        });

        $this->toast($moved > 0 ? "Classe atualizada. Encontros remarcados para {$weekday->label()}." : 'Classe atualizada.');

        return to_route('admin.classrooms.show', $classroom);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function programOptions(Church $church): array
    {
        return array_values($church->programs()->get(['id', 'name'])
            ->map(fn (Program $program) => ['id' => $program->id, 'name' => $program->name])
            ->all());
    }
}
