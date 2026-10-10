<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\StudyQuestion;
use App\Support\ChurchCalendar;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dúvidas que a turma tirou com a IA ("Tirar dúvida"), por lição, para o
 * professor preparar a aula. Anônimas: o nome de quem perguntou não sai
 * daqui. Perguntas de professores e da administração ficam de fora.
 */
class ClassroomQuestionController extends Controller
{
    /** Lições mostradas, das que tiveram dúvidas mais recentes. */
    private const LESSONS = 10;

    public function __invoke(Classroom $classroom): Response
    {
        Gate::authorize('viewInsights', $classroom);

        $staff = $classroom->teachers()->pluck('users.id');

        $questions = StudyQuestion::query()
            ->whereIn('lesson_id', $classroom->lessons()->select('id'))
            ->whereNotIn('user_id', $staff)
            ->whereHas('user', fn ($query) => $query->where('is_admin', false))
            ->latest()
            ->latest('id')
            ->get(['id', 'user_id', 'lesson_id', 'question', 'answer', 'created_at']);

        $lessonIds = $questions->pluck('lesson_id')->unique()->take(self::LESSONS);
        $lessons = Lesson::query()->whereKey($lessonIds)->get()->keyBy('id');

        return Inertia::render('admin/classrooms/questions', [
            'classroom' => ClassroomResource::make($classroom),
            'lessons' => $lessonIds->map(function (int $lessonId) use ($questions, $lessons) {
                $asked = $questions->where('lesson_id', $lessonId);
                $lesson = $lessons[$lessonId];

                return [
                    'id' => $lesson->id,
                    'title' => $lesson->displayTitle(),
                    'url' => route('lessons.show', $lesson->slug),
                    'total' => $asked->count(),
                    'people' => $asked->pluck('user_id')->unique()->count(),
                    // Perguntas iguais (muitas vêm das sugestões do painel) viram uma
                    // linha só, com quantas vezes foram feitas e a resposta mais recente.
                    'questions' => $asked
                        ->groupBy(fn (StudyQuestion $question) => mb_strtolower(trim($question->question)))
                        ->map(fn ($same) => [
                            'id' => $same->first()->id,
                            'question' => $same->first()->question,
                            'answer' => $same->first()->answer,
                            'times' => $same->count(),
                            'asked_on' => $same->first()->created_at?->setTimezone(ChurchCalendar::timezone())->toDateString(),
                        ])
                        ->values(),
                ];
            })->values(),
            'today' => ChurchCalendar::todayString(),
        ]);
    }
}
