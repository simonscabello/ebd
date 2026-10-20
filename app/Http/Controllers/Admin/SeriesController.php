<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Series\CreateSeries;
use App\Actions\Series\DeleteSeries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SeriesRequest;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\SeriesResource;
use App\Models\Classroom;
use App\Models\Series;
use App\Models\SeriesVersion;
use App\Models\User;
use App\Support\ChurchCalendar;
use App\Support\OfficialLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SeriesController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Series::class);

        $manageable = $request->user()->listedClassroomIds();

        $series = Series::query()
            ->whereIn('classroom_id', $manageable)
            ->with('classroom')
            ->withCount('lessons')
            // Última versão do estudo oficial de origem (aviso de versão nova).
            ->addSelect(['latest_source_version' => SeriesVersion::query()
                ->selectRaw('max(version)')
                ->whereColumn('series_versions.series_id', 'series.source_series_id')])
            ->orderByDesc('starts_on')
            ->orderBy('title')
            ->get();

        return Inertia::render('admin/series/index', [
            'series' => SeriesResource::collection($series),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('viewAny', Series::class);

        return Inertia::render('admin/series/form', [
            'series' => null,
            'classrooms' => $this->manageableClassrooms($request->user()),
            'defaultClassroomId' => $request->integer('classe') ?: null,
            'official' => null,
            'update_available' => null,
        ]);
    }

    public function store(SeriesRequest $request, CreateSeries $create): RedirectResponse
    {
        $classroom = Classroom::findOrFail($request->integer('classroom_id'));
        $series = $create->handle($classroom, $request->validated());

        $this->toast("Série \"{$series->title}\" criada.");

        return to_route('admin.series.index');
    }

    public function edit(Request $request, Series $series): Response
    {
        Gate::authorize('update', $series);

        $series->load('classroom')->loadCount('lessons');

        return Inertia::render('admin/series/form', [
            'series' => SeriesResource::make($series),
            'classrooms' => $this->manageableClassrooms($request->user()),
            'defaultClassroomId' => $series->classroom_id,
            'official' => $this->official($request, $series),
            'update_available' => $series->updateAvailable(),
        ]);
    }

    /**
     * Estudo da biblioteca oficial, visto pelo dono: as versões publicadas.
     *
     * @return array{next_version: int, versions: list<array{version: int, notes: string|null, created_at: string}>}|null
     */
    private function official(Request $request, Series $series): ?array
    {
        if (! OfficialLibrary::isOfficial($series->classroom) || ! $request->user()->can('own-platform')) {
            return null;
        }

        $versions = SeriesVersion::query()->where('series_id', $series->id)->orderByDesc('version')->get(['version', 'notes', 'created_at']);

        return [
            'next_version' => ((int) $versions->max('version')) + 1,
            'versions' => array_values($versions->map(fn (SeriesVersion $version) => [
                'version' => $version->version,
                'notes' => $version->notes,
                'created_at' => ChurchCalendar::formatShort($version->created_at->timezone(ChurchCalendar::timezone())),
            ])->all()),
        ];
    }

    public function update(SeriesRequest $request, Series $series): RedirectResponse
    {
        $series->update($request->safe()->only(['title', 'description', 'starts_on', 'ends_on']));

        $this->toast('Série atualizada.');

        return to_route('admin.series.index');
    }

    public function destroy(Series $series, DeleteSeries $delete): RedirectResponse
    {
        Gate::authorize('delete', $series);

        $delete->handle($series);

        $this->toast('Série excluída.');

        return to_route('admin.series.index');
    }

    private function manageableClassrooms(User $user): mixed
    {
        $ids = $user->manageableClassroomIds();

        return ClassroomResource::collection(
            Classroom::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->ordered()->get()
        );
    }
}
