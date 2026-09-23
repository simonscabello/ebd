<?php

namespace App\Actions\Engagement;

use App\Enums\Badge;
use App\Enums\SelfAssessment;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use App\Models\QuestionAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Guarda a autoavaliação numa pergunta de revisão (a última vale).
 */
class RecordQuestionAttempt
{
    public function __construct(
        private readonly AwardBadges $awardBadges,
    ) {}

    /**
     * @return list<Badge>
     */
    public function handle(User $user, Lesson $lesson, LessonQuestion $question, SelfAssessment $assessment): array
    {
        if (! $user->isMemberOf($lesson->classroom_id) || Gate::forUser($user)->denies('view', $lesson)) {
            abort(403);
        }

        if (! $question->isReview()) {
            throw ValidationException::withMessages(['self_assessment' => 'Só perguntas de revisão têm gabarito.']);
        }

        QuestionAttempt::query()->updateOrCreate(
            ['user_id' => $user->id, 'lesson_question_id' => $question->id],
            ['self_assessment' => $assessment],
        );

        return $this->awardBadges->handle($user, $lesson->series);
    }
}
