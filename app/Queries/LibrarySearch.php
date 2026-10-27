<?php

namespace App\Queries;

use App\Models\Church;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use App\Support\ChurchCalendar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Busca da biblioteca usando o full-text search do PostgreSQL.
 *
 * - Configuração "ebd_portuguese": stemming em português + sem acentos.
 * - Pesos: título e texto bíblico (A) > resumo (B) > conteúdo (C).
 * - Cada termo vira prefixo ("sant" encontra "santidade").
 * - O título da série também é pesquisado.
 */
class LibrarySearch
{
    // Marcadores improváveis em texto comum; viram <mark> após o escape.
    private const HEADLINE_START = '⟦';

    private const HEADLINE_STOP = '⟧';

    /**
     * @param  array{q?: string|null, classroom?: int|null, series?: int|null, year?: int|null}  $filters
     * @return LengthAwarePaginator<int, Lesson>
     */
    public function search(array $filters, ?User $user, int $perPage = 12): LengthAwarePaginator
    {
        $tsQuery = self::toTsQuery($filters['q'] ?? null);
        $query = $this->visible($filters, $user);

        if ($tsQuery === null) {
            // Sem data (as próximas da série ainda não têm domingo) vai para o fim.
            return $query->orderByRaw('lessons.scheduled_for DESC NULLS LAST')->orderByDesc('lessons.id')->paginate($perPage)->withQueryString();
        }

        $query
            ->leftJoin('series', 'series.id', '=', 'lessons.series_id')
            ->where(function (Builder $q) use ($tsQuery) {
                $q->whereRaw("lessons.search_vector @@ to_tsquery('ebd_portuguese', ?)", [$tsQuery])
                    ->orWhereRaw("to_tsvector('ebd_portuguese', coalesce(series.title, '')) @@ to_tsquery('ebd_portuguese', ?)", [$tsQuery]);
            })
            ->selectRaw("ts_rank(lessons.search_vector, to_tsquery('ebd_portuguese', ?)) as rank", [$tsQuery])
            ->selectRaw(
                "ts_headline('ebd_portuguese', coalesce(lessons.summary, '') || ' ' || coalesce(lessons.content, ''), to_tsquery('ebd_portuguese', ?), ?) as headline_raw",
                [$tsQuery, 'StartSel='.self::HEADLINE_START.', StopSel='.self::HEADLINE_STOP.', MaxWords=28, MinWords=12, MaxFragments=1'],
            )
            ->orderByDesc('rank')
            ->orderByRaw('lessons.scheduled_for DESC NULLS LAST');

        $results = $query->paginate($perPage)->withQueryString();

        $results->getCollection()->each(function (Lesson $lesson) {
            $lesson->setAttribute('headline', self::safeHeadline($lesson->getAttribute('headline_raw')));
        });

        return $results;
    }

    /**
     * A biblioteca sem busca: as lições agrupadas por série, cada série na
     * ordem dela. As séries atuais das classes vêm primeiro, as concluídas
     * por último. Lições sem série ficam em "Lições avulsas", por classe.
     *
     * @param  array{q?: string|null, classroom?: int|null, series?: int|null, year?: int|null}  $filters
     * @return list<array{key: string, id: int|null, title: string, classroom: string, current: bool, concluded_on: string|null, lessons: list<array{position: int|null, slug: string, title: string, bible_reference: string|null, date_short: string|null, current: bool, studied: bool}>}>
     */
    public function shelves(array $filters, ?User $user): array
    {
        $lessons = $this->visible($filters, $user)->inSequence()->get();

        // Posição na série inteira (com os rascunhos), igual ao Início.
        $sequences = [];

        foreach (Lesson::query()->whereIn('series_id', $lessons->pluck('series_id')->filter()->unique())->inSequence()->get(['id', 'series_id']) as $item) {
            $sequences[(int) $item->series_id][] = $item->id;
        }

        $shelves = $lessons
            ->groupBy(fn (Lesson $lesson) => $lesson->series_id !== null ? 'serie-'.$lesson->series_id : 'avulsas-'.$lesson->classroom_id)
            ->map(function ($items, string $key) use ($sequences) {
                $first = $items->firstOrFail();
                $series = $first->series;
                $classroom = $first->classroom;

                return [
                    'last' => (string) $items->max('studied_at'),
                    'shelf' => [
                        'key' => $key,
                        'id' => $series?->id,
                        'title' => $series === null ? 'Lições avulsas' : $series->title,
                        'classroom' => $classroom->name,
                        'current' => $series !== null && $classroom->current_series_id === $series->id,
                        'concluded_on' => $series?->concluded_at ? ChurchCalendar::formatShort($series->concluded_at) : null,
                        'lessons' => array_values($items->map(function (Lesson $lesson) use ($sequences, $classroom) {
                            $position = array_search($lesson->id, $sequences[$lesson->series_id ?? 0] ?? [], true);

                            return [
                                'position' => $position === false ? null : $position + 1,
                                'slug' => $lesson->slug,
                                'title' => $lesson->title,
                                'bible_reference' => $lesson->bible_reference,
                                'date_short' => $lesson->studied_at ? ChurchCalendar::formatShort($lesson->studied_at) : null,
                                'current' => $classroom->current_lesson_id === $lesson->id,
                                'studied' => $lesson->studied_at !== null,
                            ];
                        })->all()),
                    ],
                ];
            })
            ->sortBy([
                fn (array $a, array $b) => $b['shelf']['current'] <=> $a['shelf']['current'],
                fn (array $a, array $b) => ($a['shelf']['concluded_on'] !== null) <=> ($b['shelf']['concluded_on'] !== null),
                fn (array $a, array $b) => $b['last'] <=> $a['last'],
            ])
            ->map(fn (array $row) => $row['shelf']);

        return array_values($shelves->all());
    }

    /**
     * As lições que a pessoa pode ver na biblioteca, com os filtros.
     *
     * @param  array{q?: string|null, classroom?: int|null, series?: int|null, year?: int|null}  $filters
     * @return Builder<Lesson>
     */
    private function visible(array $filters, ?User $user): Builder
    {
        return Lesson::query()
            ->select('lessons.*')
            ->visibleTo($user)
            // Logado, a biblioteca é a da igreja: os estudos oficiais ficam fora
            // (o visitante vê os exemplos do site, que vêm de lá).
            ->when($user !== null, fn (Builder $q) => $q->whereNotIn('lessons.classroom_id', Classroom::query()->whereIn('church_id', Church::query()->where('is_official', true)->select('id'))->select('id')))
            ->with(['series', 'classroom'])
            ->when($filters['classroom'] ?? null, fn (Builder $q, $id) => $q->where('lessons.classroom_id', $id))
            ->when($filters['series'] ?? null, fn (Builder $q, $id) => $q->where('lessons.series_id', $id))
            ->when($filters['year'] ?? null, fn (Builder $q, $year) => $q->whereBetween('lessons.scheduled_for', ["{$year}-01-01", "{$year}-12-31"]));
    }

    /**
     * Converte o texto digitado em uma tsquery segura: apenas letras e números,
     * cada termo como prefixo, todos obrigatórios (AND).
     */
    public static function toTsQuery(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($input), $matches);

        $terms = array_slice(array_unique($matches[0]), 0, 8);

        if ($terms === []) {
            return null;
        }

        return implode(' & ', array_map(fn (string $term) => $term.':*', $terms));
    }

    /**
     * O trecho vem do conteúdo em Markdown (texto do professor). Escapamos tudo
     * e só então transformamos os marcadores em <mark>, evitando XSS.
     */
    private static function safeHeadline(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        // Remove a sintaxe Markdown mais comum para o trecho ficar legível.
        $plain = preg_replace(['/[#*_>`]+/u', '/\[([^\]]*)\]\([^)]*\)/u', '/\s+/u'], ['', '$1', ' '], $raw) ?? $raw;

        return str_replace(
            [self::HEADLINE_START, self::HEADLINE_STOP],
            ['<mark>', '</mark>'],
            e(trim($plain)),
        );
    }
}
