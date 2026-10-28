<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Meetings\SyncUpcomingMeeting;
use App\Enums\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Queries\ClassroomHeaderQuery;
use App\Queries\ClassroomOverviewQuery;
use App\Queries\LessonForecastQuery;
use App\Queries\NextLessonsQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página da classe (Visão geral): a lição atual no topo, as próximas da
 * série em sequência e, ao lado, quem precisa de atenção e a última chamada.
 * O domingo é só quando a lição acontece; as próximas trazem a data prevista.
 */
class ClassroomOverviewController extends Controller
{
    /** Quantas próximas lições da série aparecem em "A seguir". */
    private const UP_NEXT = 3;

    public function __invoke(
        Request $request,
        Classroom $classroom,
        ClassroomOverviewQuery $overview,
        ClassroomHeaderQuery $header,
        NextLessonsQuery $nextLessons,
        LessonForecastQuery $forecast,
        SyncUpcomingMeeting $sync,
    ): Response {
        Gate::authorize('viewInsights', $classroom);

        $sync->handle($classroom);
        $classroom->refresh();
        $user = $request->user();
        $lesson = $classroom->currentLesson?->load('series');
        $upNext = $nextLessons->for($classroom, $lesson, self::UP_NEXT);
        $dates = $forecast->for($classroom, self::UP_NEXT);

        return Inertia::render('admin/classrooms/show', [
            'classroom' => ClassroomResource::make($classroom),
            'header' => $header->for($classroom, $user),
            'overview' => $overview->for($classroom, $user),
            'current' => $lesson === null ? null : [
                ...$this->lesson($lesson),
                'bible_reference' => $lesson->bible_reference,
                'series' => $lesson->series === null ? null : ['id' => $lesson->series->id, 'title' => $lesson->series->title],
                'started_on' => $classroom->current_lesson_started_at?->toDateString(),
                'meetings_held' => ClassMeeting::query()->whereBelongsTo($classroom)->where('lesson_id', $lesson->id)
                    ->where('status', MeetingStatus::Held)->count(),
            ],
            'upNext' => $upNext->map(fn (Lesson $l) => [
                ...$this->lesson($l),
                'forecast_on' => isset($dates[$l->id]) ? $dates[$l->id]->toDateString() : null,
            ])->all(),
        ]);
    }

    /**
     * @return array{id: int, slug: string, number: int|null, display_title: string, status: string}
     */
    private function lesson(Lesson $lesson): array
    {
        return [
            'id' => $lesson->id,
            'slug' => $lesson->slug,
            'number' => $lesson->number,
            'display_title' => $lesson->displayTitle(),
            'status' => $lesson->status->value,
        ];
    }
}
