<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\StudentNote;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Anotações do professor na ficha do aluno. Nunca aparecem para o aluno.
 */
class StudentNoteController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return ['body' => ['required', 'string', 'max:5000']];
    }

    public function store(Request $request, Classroom $classroom, User $user): RedirectResponse
    {
        Gate::authorize('viewStudentProgress', [$classroom, $user]);

        $data = $request->validate($this->rules(), [], ['body' => 'anotação']);

        $note = new StudentNote(['body' => trim($data['body'])]);
        $note->classroom_id = $classroom->id;
        $note->user_id = $user->id;
        $note->author_id = $request->user()->id;
        $note->save();

        $this->toast('Anotação guardada.');

        return back();
    }

    public function update(Request $request, StudentNote $note): RedirectResponse
    {
        Gate::authorize('update', $note);

        $data = $request->validate($this->rules(), [], ['body' => 'anotação']);
        $note->update(['body' => trim($data['body'])]);

        $this->toast('Anotação atualizada.');

        return back();
    }

    public function destroy(StudentNote $note): RedirectResponse
    {
        Gate::authorize('delete', $note);

        $note->delete();

        $this->toast('Anotação apagada.');

        return back();
    }
}
