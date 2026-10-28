<?php

namespace App\Http\Controllers;

use App\Actions\Engagement\AwardBadges;
use App\Actions\Lessons\AnswerStudyQuestion;
use App\Enums\Badge;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * "Tirar dúvida" na página da lição: membros da classe (e quem a gerencia)
 * perguntam à IA sobre o estudo. Responde em JSON para o painel.
 */
class StudyQuestionController extends Controller
{
    public function store(Request $request, Lesson $lesson, AnswerStudyQuestion $answer, AwardBadges $awardBadges): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(Gate::allows('view', $lesson) && self::canAsk($user, $lesson) && AnswerStudyQuestion::isAvailable($lesson), 403);

        $question = trim((string) $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:500'],
        ])['question']);

        $asked = $answer->handle($user, $lesson, $question);

        return response()->json([
            'answer' => $asked->answer,
            'remaining' => AnswerStudyQuestion::remainingToday($user, $lesson),
            // A resposta não passa pelo Inertia: a comemoração vai junto.
            'badges' => array_map(fn (Badge $b) => ['label' => $b->label(), 'emoji' => $b->emoji()], $awardBadges->handle($user, $lesson->series)),
        ]);
    }

    public static function canAsk(?User $user, Lesson $lesson): bool
    {
        return $user !== null
            && ($user->isMemberOf($lesson->classroom_id) || $user->can('update', $lesson));
    }
}
