<?php

namespace App\Http\Controllers;

use App\Enums\ContentAudience;
use App\Http\Resources\ClassMeetingResource;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Queries\MeetingForLessonQuery;
use App\Support\ChurchCalendar;
use App\Support\WeeklyMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'study' => $this->personalStudy($request, $lesson),
        ]);
    }

    /**
     * Modo Domingo: visual limpo para conduzir/acompanhar a aula.
     */
    public function sunday(Request $request, Lesson $lesson): Response
    {
        $this->authorizeView($request, $lesson);

        $canManage = $request->user()?->can('update', $lesson) ?? false;

        return Inertia::render('lessons/sunday', [
            'lesson' => $this->present($request, $lesson),
            'canManage' => $canManage,
            // Chamada e "encerrar aula": só para quem conduz a classe.
            'conduct' => $canManage ? $this->conduct($request, $lesson) : null,
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

    /**
     * Encontro conduzido agora e lista de alunos para a chamada.
     *
     * @return array<string, mixed>|null
     */
    private function conduct(Request $request, Lesson $lesson): ?array
    {
        $meeting = app(MeetingForLessonQuery::class)->for($lesson);

        if ($meeting === null) {
            return null;
        }

        $present = $meeting->attendances()->pluck('user_id')->all();

        return [
            'meeting' => ClassMeetingResource::make($meeting)->withNotes()->resolve($request),
            'can_take_attendance' => $meeting->held_on->toDateString() <= ChurchCalendar::today()->toDateString(),
            'roster' => $lesson->classroom->students()
                ->orderBy('name')
                ->get(['users.id', 'users.name'])
                ->map(fn ($student) => ['id' => $student->id, 'name' => $student->name]),
            'present' => $present,
        ];
    }

    /**
     * Dados de estudo da própria pessoa (só para membros da classe):
     * dias marcados como lidos, autoavaliações da revisão e anotação.
     *
     * @return array<string, mixed>|null
     */
    private function personalStudy(Request $request, Lesson $lesson): ?array
    {
        $user = $request->user();

        if ($user === null || ! $user->isMemberOf($lesson->classroom_id)) {
            return null;
        }

        return [
            'checkins' => DB::table('reading_checkins')
                ->where('user_id', $user->id)
                ->where('lesson_id', $lesson->id)
                ->orderBy('read_on')
                ->pluck('read_on')
                ->map(fn ($d) => substr((string) $d, 0, 10)),
            'attempts' => (object) DB::table('question_attempts')
                ->join('lesson_questions', 'lesson_questions.id', '=', 'question_attempts.lesson_question_id')
                ->where('question_attempts.user_id', $user->id)
                ->where('lesson_questions.lesson_id', $lesson->id)
                ->pluck('self_assessment', 'lesson_question_id')
                ->all(),
            'note' => DB::table('lesson_notes')->where('user_id', $user->id)->where('lesson_id', $lesson->id)->value('body'),
            'today' => ChurchCalendar::today()->toDateString(),
        ];
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
