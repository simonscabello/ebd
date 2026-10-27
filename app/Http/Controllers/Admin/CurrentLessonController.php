<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Teaching\AdvanceLesson;
use App\Actions\Teaching\ConcludeSeries;
use App\Actions\Teaching\SetCurrentLesson;
use App\Actions\Teaching\SetCurrentSeries;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * O que a classe estuda agora: escolher a lição, concluir e passar para a
 * próxima, tornar uma série atual, concluir ou reabrir uma série.
 */
class CurrentLessonController extends Controller
{
    public function update(Request $request, Classroom $classroom, SetCurrentLesson $set): RedirectResponse
    {
        Gate::authorize('manageContent', $classroom);

        $lesson = $this->lessonOf($request, $classroom);
        $set->handle($classroom, $lesson);

        $this->toast("Agora a classe estuda \"{$lesson->displayTitle()}\".");

        return back();
    }

    public function advance(Request $request, Classroom $classroom, AdvanceLesson $advance): RedirectResponse
    {
        Gate::authorize('manageContent', $classroom);

        $next = $advance->handle($classroom, $this->lessonOf($request, $classroom)->id);

        $this->toast($next !== null
            ? "Lição concluída. Próxima: {$next->displayTitle()}."
            : 'Lição concluída. A série não tem próxima lição: escreva a próxima ou conclua a série.');

        return back();
    }

    public function series(Request $request, Classroom $classroom, SetCurrentSeries $set): RedirectResponse
    {
        Gate::authorize('manageContent', $classroom);

        $data = $request->validate(['series_id' => ['required', 'integer']]);
        $series = Series::query()->whereBelongsTo($classroom)->whereKey($data['series_id'])->firstOrFail();
        $set->handle($classroom, $series);

        $this->toast("\"{$series->title}\" agora é a série atual.");

        return back();
    }

    public function conclude(Series $series, ConcludeSeries $conclude): RedirectResponse
    {
        Gate::authorize('update', $series);

        $conclude->handle($series);

        $this->toast("\"{$series->title}\" marcada como concluída.");

        return back();
    }

    public function reopen(Series $series, ConcludeSeries $conclude): RedirectResponse
    {
        Gate::authorize('update', $series);

        $conclude->reopen($series);

        $this->toast("\"{$series->title}\" voltou para as séries em andamento.");

        return back();
    }

    private function lessonOf(Request $request, Classroom $classroom): Lesson
    {
        $data = $request->validate(['lesson_id' => ['required', 'integer']]);

        return Lesson::query()->whereBelongsTo($classroom)->whereKey($data['lesson_id'])->firstOrFail();
    }
}
