<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Lessons\SaveLessonCover;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Enviar, trocar ou remover a capa da lição na gestão.
 */
class LessonCoverController extends Controller
{
    public function store(Request $request, Lesson $lesson, SaveLessonCover $save): RedirectResponse
    {
        Gate::authorize('update', $lesson);

        $request->validate([
            'cover' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('ebd.covers.max_upload_kb')],
        ], [], ['cover' => 'capa']);

        $save->handle($lesson, (string) $request->file('cover')?->get());

        $this->toast('Capa atualizada.');

        return back();
    }

    public function destroy(Lesson $lesson, SaveLessonCover $save): RedirectResponse
    {
        Gate::authorize('update', $lesson);

        $save->remove($lesson);

        $this->toast('Capa removida.');

        return back();
    }
}
