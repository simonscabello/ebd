<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\SeriesResource;
use App\Models\Classroom;
use App\Queries\ClassroomInsightsQuery;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel de evolução da classe.
 */
class ClassroomInsightsController extends Controller
{
    public function __invoke(Classroom $classroom, ClassroomInsightsQuery $insights): Response
    {
        Gate::authorize('viewInsights', $classroom);

        return Inertia::render('admin/classrooms/insights', [
            'classroom' => ClassroomResource::make($classroom),
            'insights' => $insights->for($classroom),
            'series' => SeriesResource::collection($classroom->series()->orderByDesc('starts_on')->get()),
        ]);
    }
}
