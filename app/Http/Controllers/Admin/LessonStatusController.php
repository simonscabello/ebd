<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Lessons\ChangeLessonStatus;
use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Publicar / despublicar.
 */
class LessonStatusController extends Controller
{
    public function __invoke(Request $request, Lesson $lesson, ChangeLessonStatus $change): RedirectResponse
    {
        Gate::authorize('update', $lesson);

        $target = LessonStatus::from($request->validate([
            'status' => ['required', Rule::enum(LessonStatus::class)],
        ])['status']);

        $change->handle($lesson, $target);

        $this->toast(match ($target) {
            LessonStatus::Published => 'Lição publicada. O link já pode ser compartilhado.',
            LessonStatus::Draft => 'Lição voltou para rascunho.',
        });

        return back();
    }
}
