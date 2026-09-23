<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibraryRequest;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\LessonResource;
use App\Http\Resources\SeriesResource;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Queries\LibrarySearch;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    public function __invoke(LibraryRequest $request, LibrarySearch $library): Response
    {
        $user = $request->user();
        $filters = $request->filters();

        $results = $library->search($filters, $user);

        // Só oferece nos filtros séries que possuem alguma lição visível.
        $series = Series::query()
            ->whereIn('id', Lesson::query()->visibleTo($user)->whereNotNull('series_id')->select('series_id'))
            ->when($filters['classroom'], fn ($q, $id) => $q->where('classroom_id', $id))
            ->with('classroom')
            ->orderBy('title')
            ->get();

        return Inertia::render('library/index', [
            'filters' => [
                'q' => $filters['q'] ?? '',
                'classe' => $filters['classroom'],
                'serie' => $filters['series'],
                'ano' => $filters['year'],
            ],
            'results' => LessonResource::collection($results),
            'classrooms' => ClassroomResource::collection(Classroom::query()->ordered()->get()),
            'series' => SeriesResource::collection($series),
            'years' => $library->availableYears($user),
        ]);
    }
}
