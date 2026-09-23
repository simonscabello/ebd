<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClassMeetingResource;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\LessonResource;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use App\Support\ChurchCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $manageable = $user->manageableClassroomIds();
        $today = ChurchCalendar::today()->toDateString();

        $scope = fn (Builder $query) => $query->when($manageable !== null, fn (Builder $q) => $q->whereIn('classroom_id', $manageable));

        // Próximas lições (com encontro de hoje em diante) e lições ainda sem data.
        $upcoming = Lesson::query()
            ->tap($scope)
            ->where(fn (Builder $q) => $q
                ->whereHas('meetings', fn ($m) => $m->active()->whereDate('held_on', '>=', $today))
                ->orWhereDoesntHave('meetings', fn ($m) => $m->active()))
            ->with(['classroom', 'series'])
            ->withCount(['materials', 'questions'])
            ->withMin(['meetings as next_meeting_on' => fn ($m) => $m->active()->whereDate('held_on', '>=', $today)], 'held_on')
            // Em ordem crescente o PostgreSQL já coloca as sem data (NULL) por último.
            ->orderBy('next_meeting_on')
            ->limit(8)
            ->get();

        // Domingos que já passaram e continuam "planejados": confirmar se houve aula.
        $toConfirm = ClassMeeting::query()
            ->tap($scope)
            ->where('status', MeetingStatus::Planned)
            ->whereDate('held_on', '<', $today)
            ->with(['classroom', 'lesson'])
            ->orderByDesc('held_on')
            ->limit(5)
            ->get();

        return Inertia::render('admin/dashboard', [
            'classrooms' => ClassroomResource::collection(
                Classroom::query()->when($manageable !== null, fn ($q) => $q->whereIn('id', $manageable))->ordered()->get()
            ),
            'upcoming' => LessonResource::collection($upcoming),
            'meetingsToConfirm' => $toConfirm->map(fn (ClassMeeting $m) => [
                ...ClassMeetingResource::make($m)->resolve($request),
                'classroom' => ['name' => $m->classroom->name, 'slug' => $m->classroom->slug],
            ]),
            'isAdmin' => $user->isAdmin(),
        ]);
    }
}
