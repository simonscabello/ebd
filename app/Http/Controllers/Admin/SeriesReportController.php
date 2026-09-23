<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Models\Series;
use App\Queries\SeriesReportQuery;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Relatório do trimestre, pronto para imprimir.
 */
class SeriesReportController extends Controller
{
    public function __invoke(Series $series, SeriesReportQuery $report): Response
    {
        Gate::authorize('viewReport', $series);

        return Inertia::render('admin/series/report', [
            'classroom' => ClassroomResource::make($series->classroom),
            'report' => $report->for($series),
        ]);
    }
}
