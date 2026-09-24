<?php

namespace App\Http\Controllers;

use App\Enums\ContentAudience;
use App\Http\Resources\ClassMeetingResource;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\LessonResource;
use App\Http\Resources\SeriesResource;
use App\Models\User;
use App\Queries\CurrentLessonQuery;
use App\Support\ChurchCalendar;
use App\Support\ClassroomSelector;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home: responde "o que eu preciso estudar para o próximo domingo?".
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, CurrentLessonQuery $lessons, ClassroomSelector $selector): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        $classrooms = $selector->available($user);
        $classroom = $selector->selected($classrooms, $request->query('classe'));

        $current = $classroom ? $lessons->for($classroom, $user) : null;
        $lesson = $current?->lesson;

        $lesson?->load([
            'readings',
            'materials' => fn ($q) => $q->where('audience', ContentAudience::Student),
        ]);

        $recent = $classroom ? $lessons->recent($classroom, $user, exceptLessonId: $lesson?->id) : collect();

        // Série em estudo: a da lição da semana ou, se ela for avulsa, a da aula mais recente.
        $currentSeries = $lesson?->series;
        $currentSeries ??= $recent->first()?->series;

        return Inertia::render('home', [
            'greeting' => ChurchCalendar::greeting(),
            'today' => [
                'date' => ChurchCalendar::today()->toDateString(),
                'weekday' => ChurchCalendar::today()->dayOfWeekIso,
                'label' => ChurchCalendar::formatLong(ChurchCalendar::today()),
            ],
            'classrooms' => ClassroomResource::collection($classrooms),
            'classroom' => $classroom ? ClassroomResource::make($classroom) : null,
            'isMember' => $classroom && $user?->isMemberOf($classroom),
            'isStudent' => $classroom && $user && $user->isMemberOf($classroom) && ! $user->isTeacherOf($classroom),
            'nextLesson' => $lesson ? LessonResource::make($lesson) : null,
            'meeting' => $current?->meeting && ! $current->isFallback ? ClassMeetingResource::make($current->meeting) : null,
            'meetingIndex' => $current->meetingIndex ?? 0,
            'meetingTotal' => $current->meetingTotal ?? 0,
            'preparing' => $current->preparing ?? false,
            'cancelledBefore' => ClassMeetingResource::collection($current->cancelledBefore ?? collect()),
            'currentSeries' => $currentSeries ? SeriesResource::make($currentSeries) : null,
            'recentLessons' => LessonResource::collection($recent),
        ]);
    }
}
