<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Lessons\ReorderLessonItems;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LessonOrderController extends Controller
{
    /**
     * @param  'materials'|'questions'|'readings'|'blocks'  $relation
     */
    public function __invoke(Request $request, Lesson $lesson, string $relation, ReorderLessonItems $reorder): RedirectResponse
    {
        Gate::authorize('update', $lesson);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $reorder->handle($lesson, $relation, $validated['ids']);

        return back();
    }
}
