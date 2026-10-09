<?php

namespace App\Http\Controllers;

use App\Enums\ContentAudience;
use App\Http\Resources\ClassMeetingResource;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\LessonResource;
use App\Http\Resources\SeriesResource;
use App\Models\User;
use App\Queries\CurrentLessonQuery;
use App\Queries\StudyWeekQuery;
use App\Support\ChurchCalendar;
use App\Support\ClassroomSelector;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home: responde "o que eu preciso estudar para o próximo domingo?".
 *
 * Para quem é da classe, também é a semana de estudo: a leitura de hoje (que
 * dá para marcar ali mesmo), o progresso da semana, a sequência e o conteúdo
 * do dia. A lista completa das leituras fica em "Leituras da semana".
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, CurrentLessonQuery $lessons, ClassroomSelector $selector, StudyWeekQuery $week): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        $classrooms = $selector->available($user);
        $classroom = $selector->selected($classrooms, $request->query('classe'));

        $current = $classroom ? $lessons->for($classroom, $user) : null;
        $lesson = $current?->lesson;
        $isMember = $classroom && $user?->isMemberOf($classroom);

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
            'isMember' => $isMember,
            'isStudent' => $isMember && ! $user->isTeacherOf($classroom),
            'nextLesson' => $lesson ? LessonResource::make($lesson) : null,
            'week' => $isMember && $lesson ? $week->for($user, $classroom, $request, $current) : null,
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
