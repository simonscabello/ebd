<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\LessonResource;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use App\Queries\CurrentLessonQuery;
use App\Support\ChurchCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel da gestão: o que fazer agora em cada classe, encontros pendentes e as
 * próximas lições a preparar.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentLessonQuery $current): Response
    {
        /** @var User $user */
        $user = $request->user();
        $manageable = $user->listedClassroomIds();
        $today = ChurchCalendar::todayString();

        $scope = fn (Builder $query) => $query->whereIn('classroom_id', $manageable);

        $classrooms = Classroom::query()
            ->whereIn('id', $manageable)
            ->active()
            ->ordered()
            ->withCount('students')
            ->get();

        // A lição atual de cada classe, no próximo encontro dela.
        $scheduled = Lesson::query()
            ->tap($scope)
            ->whereIn('lessons.id', $classrooms->pluck('current_lesson_id')->filter()->values())
            ->whereHas('meetings', fn ($m) => $m->active()->whereDate('held_on', '>=', $today))
            ->with(['classroom', 'series'])
            ->withCount(['materials'])
            ->withMin(['meetings as next_meeting_on' => fn ($m) => $m->active()->whereDate('held_on', '>=', $today)], 'held_on')
            ->orderBy('next_meeting_on')
            ->limit(8)
            ->get();

        // Igreja sem nenhuma lição: o painel oferece cadastrar a primeira.
        $hasLessons = $scheduled->isNotEmpty() || Lesson::query()->tap($scope)->exists();

        // Domingos que já passaram e continuam "planejados": fazer a chamada ou confirmar.
        $pending = ClassMeeting::query()
            ->tap($scope)
            ->where('status', MeetingStatus::Planned)
            ->whereDate('held_on', '<', $today)
            ->with(['classroom', 'lesson'])
            ->orderByDesc('held_on')
            ->limit(8)
            ->get();

        return Inertia::render('admin/dashboard', [
            'classrooms' => $classrooms->map(function (Classroom $classroom) use ($current, $user, $today) {
                $week = $current->for($classroom, $user);
                $meeting = $week->meeting;

                return [
                    ...ClassroomResource::make($classroom)->resolve(),
                    'students_count' => (int) $classroom->getAttribute('students_count'),
                    'week' => $meeting === null ? null : [
                        'meeting_id' => $meeting->id,
                        'held_on' => $meeting->held_on->toDateString(),
                        'is_today' => $meeting->held_on->toDateString() === $today,
                        'has_attendance' => $meeting->hasAttendance(),
                        'title' => $meeting->title,
                        'lesson' => $week->lesson ? [
                            'slug' => $week->lesson->slug,
                            'display_title' => $week->lesson->displayTitle(),
                            'status' => $week->lesson->status->value,
                        ] : null,
                    ],
                ];
            }),
            'pending' => $pending->map(fn (ClassMeeting $m) => [
                'id' => $m->id,
                'held_on' => $m->held_on->toDateString(),
                'lesson' => $m->lesson?->displayTitle(),
                'title' => $m->title,
                'classroom' => ['name' => $m->classroom->name, 'slug' => $m->classroom->slug],
            ]),
            'scheduled' => $scheduled->map(fn (Lesson $lesson) => [
                ...LessonResource::make($lesson)->resolve($request),
                'next_meeting_on' => substr((string) $lesson->getAttribute('next_meeting_on'), 0, 10),
            ]),
            'hasLessons' => $hasLessons,
        ]);
    }
}
