<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClassroomResource;
use App\Http\Resources\LessonResource;
use App\Http\Resources\SeriesResource;
use App\Models\Classroom;
use App\Models\User;
use App\Queries\NextLessonQuery;
use App\Support\ChurchCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home: responde "o que eu preciso estudar para o próximo domingo?".
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, NextLessonQuery $lessons): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        $classrooms = $this->availableClassrooms($user);
        $classroom = $this->selectedClassroom($classrooms, $request->query('classe'), $user);

        $nextLesson = $classroom ? $lessons->next($classroom, $user) : null;
        $recent = $classroom ? $lessons->recent($classroom, $user) : collect();

        // Série em estudo: a da próxima aula ou, se ela for avulsa, a da aula mais recente.
        $currentSeries = $nextLesson?->series;
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
            'nextLesson' => $nextLesson ? LessonResource::make($nextLesson) : null,
            'currentSeries' => $currentSeries ? SeriesResource::make($currentSeries) : null,
            'recentLessons' => LessonResource::collection($recent),
        ]);
    }

    /**
     * Classes ativas. Membros veem primeiro as próprias classes.
     *
     * @return Collection<int, Classroom>
     */
    private function availableClassrooms(?User $user): Collection
    {
        $classrooms = Classroom::query()->active()->ordered()->get();

        if ($user === null) {
            return $classrooms;
        }

        $mine = $user->memberClassroomIds();

        return $classrooms
            ->sortBy(fn (Classroom $c) => in_array($c->id, $mine, true) ? 0 : 1)
            ->values();
    }

    /**
     * @param  Collection<int, Classroom>  $classrooms
     */
    private function selectedClassroom(Collection $classrooms, mixed $slug, ?User $user): ?Classroom
    {
        if (is_string($slug) && ($found = $classrooms->firstWhere('slug', $slug))) {
            return $found;
        }

        return $classrooms->first();
    }
}
