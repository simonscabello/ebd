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
            LessonStatus::Published => match (true) {
                $change->notificationFailed => 'Lição publicada, mas o aviso no celular falhou. O erro ficou registrado.',
                $change->notifiedDevices > 0 => "Lição publicada. Aviso enviado para {$change->notifiedDevices} aparelho(s) da classe.",
                default => 'Lição publicada. Ninguém da classe ativou os lembretes ainda.',
            },
            LessonStatus::Draft => 'Lição voltou para rascunho.',
        });

        return back();
    }
}
