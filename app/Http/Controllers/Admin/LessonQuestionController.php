<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LessonQuestionRequest;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class LessonQuestionController extends Controller
{
    public function store(LessonQuestionRequest $request, Lesson $lesson): RedirectResponse
    {
        $lesson->questions()->create($request->validated());

        $this->toast('Pergunta adicionada.');

        return back();
    }

    public function update(LessonQuestionRequest $request, Lesson $lesson, LessonQuestion $question): RedirectResponse
    {
        $question->update($request->validated());

        $this->toast('Pergunta atualizada.');

        return back();
    }

    public function destroy(Lesson $lesson, LessonQuestion $question): RedirectResponse
    {
        Gate::authorize('update', $lesson);

        $question->delete();

        $this->toast('Pergunta removida.');

        return back();
    }
}
