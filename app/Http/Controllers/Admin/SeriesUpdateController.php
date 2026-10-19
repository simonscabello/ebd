<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Series;
use App\Models\SeriesVersion;
use App\Queries\SeriesUpdateComparison;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Versão nova do estudo oficial de uma cópia: a comparação e "marcar como
 * vista". Nada é aplicado na cópia: ajustar é manual.
 */
class SeriesUpdateController extends Controller
{
    public function show(Series $series, SeriesUpdateComparison $comparison): Response|RedirectResponse
    {
        Gate::authorize('update', $series);
        abort_if($series->source_series_id === null, 404);

        $result = $comparison->for($series);

        if ($result === null) {
            return to_route('admin.series.edit', $series);
        }

        return Inertia::render('admin/series/update', [
            'series' => ['id' => $series->id, 'title' => $series->title],
            'comparison' => $result,
        ]);
    }

    public function acknowledge(Series $series): RedirectResponse
    {
        Gate::authorize('update', $series);
        abort_if($series->source_series_id === null, 404);

        $latest = SeriesVersion::query()->where('series_id', $series->source_series_id)->max('version');

        if ($latest !== null) {
            $series->forceFill(['source_version' => (int) $latest])->save();
        }

        $this->toast('Pronto. O aviso volta quando sair outra versão.');

        return to_route('admin.series.edit', $series);
    }
}
