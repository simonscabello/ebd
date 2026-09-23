<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\LessonResource;
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

        $scope = fn (Builder $query) => $query->when($manageable !== null, fn (Builder $q) => $q->whereIn('classroom_id', $manageable));

        $upcoming = Lesson::query()
            ->tap($scope)
            ->where(fn (Builder $q) => $q
                ->whereDate('scheduled_for', '>=', ChurchCalendar::today()->toDateString())
                ->orWhereNull('scheduled_for'))
            ->where('status', '!=', LessonStatus::Completed)
            ->with(['classroom', 'series'])
            ->withCount(['materials', 'questions'])
            ->orderByRaw('scheduled_for IS NULL, scheduled_for')
            ->limit(8)
            ->get();

        $pendingCompletion = Lesson::query()
            ->tap($scope)
            ->where('status', LessonStatus::Published)
            ->whereDate('scheduled_for', '<', ChurchCalendar::today()->toDateString())
            ->with(['classroom', 'series'])
            ->orderByDesc('scheduled_for')
            ->limit(5)
            ->get();

        return Inertia::render('admin/dashboard', [
            'classrooms' => ClassroomResource::collection(
                Classroom::query()->when($manageable !== null, fn ($q) => $q->whereIn('id', $manageable))->ordered()->get()
            ),
            'upcoming' => LessonResource::collection($upcoming),
            'pendingCompletion' => LessonResource::collection($pendingCompletion),
            'isAdmin' => $user->isAdmin(),
        ]);
    }
}
