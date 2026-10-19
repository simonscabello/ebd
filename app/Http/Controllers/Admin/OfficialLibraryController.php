<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Library\CopySeriesToClassroom;
use App\Enums\ContentAudience;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Series;
use App\Models\SeriesVersion;
use App\Models\User;
use App\Support\ChurchCalendar;
use App\Support\Markdown;
use App\Support\OfficialLibrary;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Biblioteca oficial na gestão: os estudos do dono, livres para qualquer
 * igreja ler e copiar para uma turma. O texto mostrado é o da última versão
 * publicada (o retrato), nunca o rascunho do dono.
 */
class OfficialLibraryController extends Controller
{
    public function index(): Response
    {
        $studies = Series::query()
            ->where('classroom_id', OfficialLibrary::classroom()->id)
            ->whereHas('versions')
            ->orderBy('title')
            ->get()
            ->map(function (Series $series) {
                $latest = $series->latestVersion();

                return [
                    'id' => $series->id,
                    'title' => $latest->snapshot['title'],
                    'description' => $latest->snapshot['description'],
                    'lessons_count' => count($latest->snapshot['lessons']),
                    'version' => $latest->version,
                    'published_at' => ChurchCalendar::formatShort($latest->created_at->timezone(ChurchCalendar::timezone())),
                ];
            });

        return Inertia::render('admin/library/index', [
            'studies' => array_values($studies->all()),
        ]);
    }

    public function show(Request $request, Series $series): Response
    {
        $latest = $this->latestOf($series);

        return Inertia::render('admin/library/show', [
            'study' => [
                'id' => $series->id,
                'title' => $latest->snapshot['title'],
                'description' => $latest->snapshot['description'],
                'version' => $latest->version,
                'notes' => $latest->notes,
                'lessons' => array_map(fn (array $lesson) => [
                    'number' => $lesson['number'],
                    'title' => $lesson['title'],
                    'summary' => $lesson['summary'],
                    'bible_reference' => $lesson['bible_reference'],
                    'key_verse' => $lesson['key_verse'],
                    'goal' => $lesson['goal'],
                    'content_html' => Markdown::toHtml($lesson['content']),
                    'blocks' => array_values(array_map(fn (array $block) => [
                        'title' => $block['title'],
                        'html' => Markdown::toHtml($block['body']),
                    ], array_filter($lesson['blocks'], fn (array $block) => $block['audience'] === ContentAudience::Student->value))),
                    'readings' => array_map(fn (array $reading) => $reading['reference'], $lesson['readings']),
                ], $latest->snapshot['lessons']),
            ],
            'classrooms' => array_values($this->targets($request->user())->map(fn (Classroom $classroom) => [
                'id' => $classroom->id,
                'name' => $classroom->name,
            ])->all()),
        ]);
    }

    public function copy(Request $request, Series $series, CopySeriesToClassroom $copy): RedirectResponse
    {
        $latest = $this->latestOf($series);
        $targets = $this->targets($request->user());

        $validated = $request->validate([
            'classroom_id' => ['required', 'integer', Rule::in($targets->modelKeys())],
        ], ['classroom_id.in' => 'Escolha uma das suas turmas.']);

        $target = $targets->firstOrFail(fn (Classroom $classroom) => $classroom->id === (int) $validated['classroom_id']);
        $copied = $copy->handle($request->user(), $latest, $target);

        $this->toast('Estudo copiado. As lições estão em rascunho: revise e publique quando quiser.');

        return to_route('admin.series.edit', $copied);
    }

    private function latestOf(Series $series): SeriesVersion
    {
        abort_unless(OfficialLibrary::isOfficial($series->classroom), 404);

        return $series->latestVersion() ?? abort(404);
    }

    /**
     * Turmas em que a pessoa pode usar o estudo (as que gerencia, fora da
     * própria biblioteca).
     *
     * @return Collection<int, Classroom>
     */
    private function targets(User $user)
    {
        return Classroom::query()
            ->whereIn('id', $user->manageableClassroomIds())
            ->whereHas('church', fn ($query) => $query->where('is_official', false))
            ->orderBy('name')
            ->get();
    }
}
