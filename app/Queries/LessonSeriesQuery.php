<?php

namespace App\Queries;

use App\Models\Lesson;
use App\Models\User;

/**
 * A série da lição atual no Início do aluno: em que lição a classe está
 * ("Lição 5 de 8", pelo número da revista; sem número, pela posição na
 * série), quantas já estudou e as próximas, na ordem da série.
 * Uma próxima que a pessoa ainda não pode ver (rascunho) vai sem título:
 * aparece como "em preparação".
 */
class LessonSeriesQuery
{
    /**
     * @return array{id: int, title: string, index: int, total: int, number: int, of: int, studied: int, upcoming: list<array{index: int, title: string|null, slug: string|null, bible_reference: string|null}>}|null
     */
    public function for(Lesson $lesson, ?User $user, int $limit = 3): ?array
    {
        $series = $lesson->series;

        if ($series === null) {
            return null;
        }

        $sequence = Lesson::query()->whereBelongsTo($series)->inSequence()
            ->get(['id', 'number', 'title', 'slug', 'bible_reference', 'studied_at'])
            ->values();
        $position = $sequence->search(fn (Lesson $item) => $item->id === $lesson->id);

        if ($position === false) {
            return null;
        }

        $visible = Lesson::query()->visibleTo($user)->whereBelongsTo($series)->pluck('id')->all();

        $upcoming = array_values($sequence->slice($position + 1)
            ->whereNull('studied_at')
            ->take($limit)
            ->map(fn (Lesson $item, int $key) => in_array($item->id, $visible, true)
                ? ['index' => $item->number ?? $key + 1, 'title' => $item->title, 'slug' => $item->slug, 'bible_reference' => $item->bible_reference]
                : ['index' => $item->number ?? $key + 1, 'title' => null, 'slug' => null, 'bible_reference' => null])
            ->all());

        return [
            'id' => $series->id,
            'title' => $series->title,
            // index/total: a barrinha (ordem da série); number/of: o rótulo (número da revista).
            'index' => $position + 1,
            'total' => $sequence->count(),
            'number' => $lesson->number ?? $position + 1,
            'of' => $lesson->number !== null ? (int) $sequence->max('number') : $sequence->count(),
            'studied' => $sequence->whereNotNull('studied_at')->count(),
            'upcoming' => $upcoming,
        ];
    }
}
