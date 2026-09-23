<?php

namespace App\Http\Controllers;

use App\Actions\Engagement\RecordQuestionAttempt;
use App\Enums\SelfAssessment;
use App\Http\Controllers\Concerns\CelebratesBadges;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuestionAttemptController extends Controller
{
    use CelebratesBadges;

    public function __invoke(Request $request, Lesson $lesson, LessonQuestion $question, RecordQuestionAttempt $record): RedirectResponse
    {
        $data = $request->validate([
            'self_assessment' => ['required', Rule::enum(SelfAssessment::class)],
        ]);

        /** @var User $user */
        $user = $request->user();

        $this->celebrate($record->handle($user, $lesson, $question, SelfAssessment::from($data['self_assessment'])));

        return back();
    }
}
