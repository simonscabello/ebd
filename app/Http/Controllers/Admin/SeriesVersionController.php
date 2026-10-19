<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Library\PublishSeriesVersion;
use App\Http\Controllers\Controller;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * "Publicar versão" de um estudo oficial (só o dono do sistema).
 */
class SeriesVersionController extends Controller
{
    public function store(Request $request, Series $series, PublishSeriesVersion $publish): RedirectResponse
    {
        Gate::authorize('own-platform');

        $notes = $request->validate(['notes' => ['nullable', 'string', 'max:2000']])['notes'] ?? null;

        $version = $publish->handle($request->user(), $series, $notes);

        $this->toast("Versão {$version->version} publicada na biblioteca.");

        return back();
    }
}
