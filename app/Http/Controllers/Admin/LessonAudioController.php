<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Lessons\RequestLessonAudio;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * "Gerar áudio" / "Regenerar áudio" do estudo.
 */
class LessonAudioController extends Controller
{
    public function __invoke(Lesson $lesson, RequestLessonAudio $request): RedirectResponse
    {
        Gate::authorize('update', $lesson);

        $this->toast(match ($request->handle($lesson)) {
            RequestLessonAudio::STARTED => 'Gerando o áudio do estudo. Pode levar alguns minutos.',
            RequestLessonAudio::ALREADY_RUNNING => 'O áudio já está sendo gerado. Aguarde terminar.',
            default => 'O áudio já está atualizado com o estudo.',
        }, 'info');

        return back();
    }
}
