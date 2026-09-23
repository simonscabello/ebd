<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LessonReadingRequest;
use App\Models\Lesson;
use App\Models\LessonReading;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class LessonReadingController extends Controller
{
    public function store(LessonReadingRequest $request, Lesson $lesson): RedirectResponse
    {
        $lesson->readings()->create($request->validated());

        $this->toast('Leitura adicionada.');

        return back();
    }

    public function update(LessonReadingRequest $request, Lesson $lesson, LessonReading $reading): RedirectResponse
    {
        $reading->update($request->validated());

        $this->toast('Leitura atualizada.');

        return back();
    }

    public function destroy(Lesson $lesson, LessonReading $reading): RedirectResponse
    {
        Gate::authorize('update', $lesson);

        $reading->delete();

        $this->toast('Leitura removida.');

        return back();
    }
}
