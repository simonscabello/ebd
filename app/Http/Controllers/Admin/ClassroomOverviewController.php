<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use App\Queries\ClassroomOverviewQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Resumo da classe: a primeira aba da gestão de cada classe.
 */
class ClassroomOverviewController extends Controller
{
    public function __invoke(Request $request, Classroom $classroom, ClassroomOverviewQuery $overview): Response
    {
        Gate::authorize('viewInsights', $classroom);

        return Inertia::render('admin/classrooms/show', [
            'classroom' => ClassroomResource::make($classroom),
            'overview' => $overview->for($classroom, $request->user()),
            // Próximas lições para compartilhar no grupo (paginadas à parte).
            'upcoming' => fn () => $overview->upcoming($classroom, $request->user()),
            'canEdit' => $request->user()->can('update', $classroom),
        ]);
    }
}
