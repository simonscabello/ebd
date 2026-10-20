<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\SeriesVersion;
use App\Support\ChurchCalendar;
use App\Support\OfficialLibrary;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Biblioteca oficial do dono: os estudos que as igrejas copiam. Por dentro é
 * a turma "Estudos oficiais", mas aqui aparece como área própria; editar as
 * lições e publicar versões continua pelas telas de sempre.
 */
class LibraryController extends Controller
{
    public function index(): Response
    {
        $classroom = OfficialLibrary::classroom();

        $series = Series::query()
            ->where('classroom_id', $classroom->id)
            ->with(['lessons' => fn ($query) => $query->orderByRaw('number is null, number')->orderBy('id')])
            ->orderBy('title')
            ->get();

        $versions = SeriesVersion::query()
            ->whereIn('series_id', $series->modelKeys())
            ->selectRaw('series_id, max(version) as version, max(created_at) as published_at')
            ->groupBy('series_id')
            ->get()
            ->keyBy('series_id');

        $loose = Lesson::query()
            ->where('classroom_id', $classroom->id)
            ->whereNull('series_id')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('owner/library', [
            'classroom_id' => $classroom->id,
            'series' => array_values($series->map(fn (Series $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'latest_version' => ($version = $versions->get($item->id)) ? (int) $version->getAttribute('version') : null,
                'published_at' => $version
                    ? ChurchCalendar::formatShort(Carbon::parse((string) $version->getAttribute('published_at'))->timezone(ChurchCalendar::timezone()))
                    : null,
                'lessons' => array_values($item->lessons->map(fn (Lesson $lesson) => self::lesson($lesson))->all()),
            ])->all()),
            'loose' => array_values($loose->map(fn (Lesson $lesson) => self::lesson($lesson))->all()),
        ]);
    }

    /**
     * @return array{id: int, title: string, status: string}
     */
    private static function lesson(Lesson $lesson): array
    {
        return [
            'id' => $lesson->id,
            'title' => $lesson->displayTitle(),
            'status' => $lesson->status->value,
        ];
    }
}
