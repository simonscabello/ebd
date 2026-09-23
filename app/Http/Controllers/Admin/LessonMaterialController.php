<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Materials\DeleteLessonMaterial;
use App\Actions\Materials\StoreLessonMaterial;
use App\Actions\Materials\UpdateLessonMaterial;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LessonMaterialRequest;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class LessonMaterialController extends Controller
{
    public function store(LessonMaterialRequest $request, Lesson $lesson, StoreLessonMaterial $store): RedirectResponse
    {
        $store->handle($lesson, $request->safe()->except('file'), $request->file('file'));

        $this->toast('Material adicionado.');

        return back();
    }

    public function update(LessonMaterialRequest $request, Lesson $lesson, LessonMaterial $material, UpdateLessonMaterial $update): RedirectResponse
    {
        $update->handle($material, $request->safe()->except('file'), $request->file('file'));

        $this->toast('Material atualizado.');

        return back();
    }

    public function destroy(Lesson $lesson, LessonMaterial $material, DeleteLessonMaterial $delete): RedirectResponse
    {
        Gate::authorize('update', $lesson);

        $delete->handle($material);

        $this->toast('Material removido.');

        return back();
    }
}
