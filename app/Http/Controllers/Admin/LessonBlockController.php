<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Lessons\SyncLessonSearchText;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LessonBlockRequest;
use App\Models\Lesson;
use App\Models\LessonBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Blocos de conteúdo: roteiro, contexto, teologia, curiosidades, conceitos...
 */
class LessonBlockController extends Controller
{
    public function __construct(
        private readonly SyncLessonSearchText $syncSearch,
    ) {}

    public function store(LessonBlockRequest $request, Lesson $lesson): RedirectResponse
    {
        $lesson->blocks()->create($request->validated());
        $this->syncSearch->handle($lesson);

        $this->toast('Bloco adicionado.');

        return back();
    }

    public function update(LessonBlockRequest $request, Lesson $lesson, LessonBlock $block): RedirectResponse
    {
        $block->update($request->validated());
        $this->syncSearch->handle($lesson);

        $this->toast('Bloco atualizado.');

        return back();
    }

    public function destroy(Lesson $lesson, LessonBlock $block): RedirectResponse
    {
        Gate::authorize('update', $lesson);

        $block->delete();
        $this->syncSearch->handle($lesson);

        $this->toast('Bloco removido.');

        return back();
    }
}
