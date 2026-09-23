<?php

namespace App\Queries;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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

        $query = Lesson::query()
            ->select('lessons.*')
            ->visibleTo($user)
            ->with(['series', 'classroom'])
            ->when($filters['classroom'] ?? null, fn (Builder $q, $id) => $q->where('lessons.classroom_id', $id))
            ->when($filters['series'] ?? null, fn (Builder $q, $id) => $q->where('lessons.series_id', $id))
            ->when($filters['year'] ?? null, fn (Builder $q, $year) => $q->whereBetween('lessons.scheduled_for', ["{$year}-01-01", "{$year}-12-31"]));

        if ($tsQuery === null) {
            return $query->orderByDesc('lessons.scheduled_for')->orderByDesc('lessons.id')->paginate($perPage)->withQueryString();
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
            ->orderByDesc('lessons.scheduled_for');

        $results = $query->paginate($perPage)->withQueryString();

        $results->getCollection()->each(function (Lesson $lesson) {
            $lesson->setAttribute('headline', self::safeHeadline($lesson->getAttribute('headline_raw')));
        });

        return $results;
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

    /**
     * Anos que possuem lições visíveis, para o filtro.
     *
     * @return list<int>
     */
    public function availableYears(?User $user): array
    {
        return array_values(Lesson::query()
            ->visibleTo($user)
            ->whereNotNull('scheduled_for')
            ->select(DB::raw('DISTINCT EXTRACT(YEAR FROM scheduled_for)::int AS year'))
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->all());
    }
}
