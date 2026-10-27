<?php

namespace App\Http\Controllers;

use App\Actions\Lessons\AnswerStudyQuestion;
use App\Enums\ContentAudience;
use App\Http\Resources\LessonAudioResource;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Support\ChurchCalendar;
use App\Support\ChurchFeatures;
use App\Support\ReadingWeeks;
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
            // "Ouvir estudo"; a geração fica na gestão (edição da lição).
            'audio' => LessonAudioResource::file($lesson),
            'helper' => $this->helper($request, $lesson),
        ])->withViewData([
            // Prévia do link: o WhatsApp não roda o JavaScript da página, e só
            // enxerga a capa de lição pública (as outras pedem login).
            'ogImage' => $lesson->isPublic() ? $lesson->coverUrl() : null,
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
     * Dados de estudo da própria pessoa (só para membros da classe):
     * dias do plano de leitura já marcados como lidos e anotação.
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
            'checked_weekdays' => ReadingWeeks::constrain(
                DB::table('reading_checkins')->where('user_id', $user->id)->where('lesson_id', $lesson->id),
                $lesson->id,
            )
                ->orderBy('weekday')
                ->pluck('weekday')
                ->map(fn ($d) => (int) $d),
            'note' => DB::table('lesson_notes')->where('user_id', $user->id)->where('lesson_id', $lesson->id)->value('body'),
            'today' => ChurchCalendar::today()->toDateString(),
        ];
    }

    /**
     * "Tirar dúvida": só para membros da classe (e quem a gerencia).
     *
     * @return array{remaining: int, daily_limit: int}|null
     */
    private function helper(Request $request, Lesson $lesson): ?array
    {
        $user = $request->user();

        if ($user === null || ! AnswerStudyQuestion::isAvailable($lesson) || ! StudyQuestionController::canAsk($user, $lesson)) {
            return null;
        }

        return [
            'remaining' => AnswerStudyQuestion::remainingToday($user, $lesson),
            'daily_limit' => ChurchFeatures::helperDailyLimit($lesson),
        ];
    }

    private function present(Request $request, Lesson $lesson): LessonResource
    {
        $teacher = Gate::allows('viewTeacherContent', $lesson);
        $audience = fn ($query) => $teacher ? $query : $query->where('audience', ContentAudience::Student);

        // O filtro é na consulta: conteúdo do professor nem chega a ser carregado
        // para alunos e visitantes.
        $lesson->load([
            'classroom', 'series', 'authors', 'readings',
            'materials' => $audience,
            'blocks' => $audience,
            'meetings' => fn ($query) => $query->active(),
        ]);

        return LessonResource::make($lesson)
            ->withContent()
            ->withTeacherContent($teacher);
    }
}
