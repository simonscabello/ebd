<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Programas da igreja (Escola Bíblica Dominical, Discipulado...): as turmas de
 * cada um e quem coordena. Tela da administração da igreja.
 */
class ProgramController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Program::class);

        $church = $this->church($request);
        $church->defaultProgram();

        $programs = $church->programs()
            ->with([
                'classrooms' => fn ($query) => $query->ordered(),
                'coordinators' => fn ($query) => $query->orderBy('name'),
            ])
            ->get();

        return Inertia::render('admin/programs/index', [
            'programs' => $programs->map(fn (Program $program) => [
                'id' => $program->id,
                'name' => $program->name,
                'short_name' => $program->short_name,
                'classrooms' => $program->classrooms->map(fn (Classroom $classroom) => [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'slug' => $classroom->slug,
                    'is_active' => $classroom->is_active,
                    'meeting_weekday_label' => $classroom->meeting_weekday->label(),
                ]),
                'coordinators' => $program->coordinators->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Program::class);

        $church = $this->church($request);
        $data = $this->validated($request, $church);

        $program = $church->programs()->create([
            ...$data,
            'slug' => $this->uniqueSlug($church, $data['name']),
            'position' => (int) $church->programs()->max('position') + 1,
        ]);

        $this->toast("Programa \"{$program->name}\" criado.");

        return back();
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        Gate::authorize('update', $program);

        $program->update($this->validated($request, $program->church, $program));
        $this->toast('Programa atualizado.');

        return back();
    }

    /**
     * @return array{name: string, short_name: string}
     */
    private function validated(Request $request, Church $church, ?Program $program = null): array
    {
        /** @var array{name: string, short_name: string} */
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('programs')->where('church_id', $church->id)->ignore($program)],
            'short_name' => ['required', 'string', 'max:30'],
        ], [], ['name' => 'nome', 'short_name' => 'nome curto']);
    }

    private function church(Request $request): Church
    {
        $church = $request->user()->currentChurch();
        abort_if($church === null, 403);

        return $church;
    }

    private function uniqueSlug(Church $church, string $name): string
    {
        $base = Str::slug($name) ?: 'programa';
        $slug = $base;
        $suffix = 2;

        while ($church->programs()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
