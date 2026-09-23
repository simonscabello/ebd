<?php

namespace App\Http\Controllers;

use App\Enums\ContentAudience;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Support\WeeklyMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    /**
     * Página de preparação da semana: /licoes/{slug}.
     */
    public function show(Request $request, Lesson $lesson): Response
    {
        $this->authorizeView($request, $lesson);

        return Inertia::render('lessons/show', [
            'lesson' => $this->present($request, $lesson),
            'canManage' => $request->user()?->can('update', $lesson) ?? false,
            'shareText' => app(WeeklyMessage::class)->share($lesson),
        ]);
    }

    /**
     * Modo Domingo: visual limpo para conduzir/acompanhar a aula.
     */
    public function sunday(Request $request, Lesson $lesson): Response
    {
        $this->authorizeView($request, $lesson);

        return Inertia::render('lessons/sunday', [
            'lesson' => $this->present($request, $lesson),
            'canManage' => $request->user()?->can('update', $lesson) ?? false,
        ]);
    }

    private function authorizeView(Request $request, Lesson $lesson): void
    {
        // Visitante tentando abrir lição restrita a membros: manda para o login
        // (e volta para a lição depois). Para quem já está logado, 404 evita
        // confirmar a existência de conteúdo restrito.
        if ($request->user() === null && ! Gate::allows('view', $lesson) && $lesson->status->isVisible()) {
            abort(redirect()->guest(route('login')));
        }

        if (Gate::denies('view', $lesson)) {
            abort(404);
        }
    }

    private function present(Request $request, Lesson $lesson): LessonResource
    {
        $teacher = Gate::allows('viewTeacherContent', $lesson);
        $audience = fn ($query) => $teacher ? $query : $query->where('audience', ContentAudience::Student);

        // O filtro é na consulta: conteúdo do professor nem chega a ser carregado
        // para alunos e visitantes.
        $lesson->load([
            'classroom', 'series', 'authors', 'readings', 'questions',
            'materials' => $audience,
            'blocks' => $audience,
            'meetings' => fn ($query) => $query->active(),
        ]);

        return LessonResource::make($lesson)
            ->withContent()
            ->withTeacherContent($teacher);
    }
}
