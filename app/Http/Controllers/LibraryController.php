<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibraryRequest;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\LessonResource;
use App\Models\Church;
use App\Models\Classroom;
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
        $browsing = blank($filters['q'] ?? null);

        return Inertia::render('library/index', [
            'filters' => [
                'q' => $filters['q'] ?? '',
                'classe' => $filters['classroom'],
                'serie' => $filters['series'],
                'ano' => $filters['year'],
            ],
            'results' => LessonResource::collection($results),
            // Logado, os estudos oficiais ficam fora da biblioteca (ver LibrarySearch): a classe deles também.
            'classrooms' => ClassroomResource::collection(Classroom::query()
                ->whereIn('church_id', Church::idsVisibleTo($user))
                ->when($user !== null, fn ($q) => $q->whereNotIn('church_id', Church::query()->where('is_official', true)->select('id')))
                ->ordered()
                ->get()),
            // Sem busca, a biblioteca é por série.
            'shelves' => $browsing ? $library->shelves($filters, $user) : [],
        ]);
    }
}
