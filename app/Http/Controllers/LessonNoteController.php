<?php

namespace App\Http\Controllers;

use App\Actions\Engagement\AwardBadges;
use App\Http\Controllers\Concerns\CelebratesBadges;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Anotação pessoal do aluno numa lição. Sempre consultada pelo usuário logado:
 * não existe rota para ler a anotação de outra pessoa.
 */
class LessonNoteController extends Controller
{
    use CelebratesBadges;

    public function update(Request $request, Lesson $lesson, AwardBadges $awardBadges): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->isMemberOf($lesson->classroom_id) && Gate::allows('view', $lesson), 403);

        $body = trim((string) $request->validate(['body' => ['nullable', 'string', 'max:20000']])['body']);

        if ($body === '') {
            LessonNote::query()->where('user_id', $user->id)->where('lesson_id', $lesson->id)->delete();
        } else {
            LessonNote::query()->updateOrCreate(
                ['user_id' => $user->id, 'lesson_id' => $lesson->id],
                ['body' => $body],
            );

            $this->celebrate($awardBadges->handle($user, $lesson->series), $lesson->classroom->meeting_weekday);
        }

        return back();
    }
}
