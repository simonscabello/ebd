<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Queries\ClassroomHeaderQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aba Lições da classe: as séries em sequência, a atual primeiro e aberta,
 * as em andamento depois e as concluídas no fim; lições avulsas à parte.
 */
class ClassroomLessonsController extends Controller
{
    public function __invoke(Request $request, Classroom $classroom, ClassroomHeaderQuery $header): Response
    {
        Gate::authorize('manageContent', $classroom);

        $lessons = Lesson::query()->whereBelongsTo($classroom)->inSequence()->get()->groupBy('series_id');
        $row = fn (Lesson $l) => [
            'id' => $l->id,
            'slug' => $l->slug,
            'number' => $l->number,
            'display_title' => $l->displayTitle(),
            'cover_url' => $l->coverUrl(thumbnail: true),
            'status' => $l->status->value,
            'studied' => $l->studied_at !== null,
            'is_current' => $l->id === $classroom->current_lesson_id,
        ];

        $series = $classroom->series()->get()
            ->sortBy(fn (Series $s) => [
                $s->id === $classroom->current_series_id ? 0 : ($s->concluded_at === null ? 1 : 2),
                // Em andamento: a mais nova primeiro; concluídas: a concluída por último primeiro.
                -(($s->concluded_at ?? $s->starts_on ?? $s->created_at)?->getTimestamp() ?? 0),
                -$s->id,
            ])
            ->values()
            ->map(fn (Series $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'is_current' => $s->id === $classroom->current_series_id,
                'concluded_on' => $s->concluded_at?->toDateString(),
                'update_available' => $s->updateAvailable(),
                'lessons' => $this->rows($lessons->get($s->id), $row),
            ]);

        return Inertia::render('admin/classrooms/lessons', [
            'classroom' => ClassroomResource::make($classroom),
            'header' => $header->for($classroom, $request->user()),
            'series' => $series,
            'standalone' => $this->rows($lessons->get(''), $row),
        ]);
    }

    /**
     * @param  Collection<int, Lesson>|null  $lessons
     * @return list<array<string, mixed>>
     */
    private function rows(?Collection $lessons, \Closure $row): array
    {
        return $lessons === null ? [] : array_values($lessons->map($row)->all());
    }
}
