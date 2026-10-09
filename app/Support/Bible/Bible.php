<?php

namespace App\Support\Bible;

use App\Models\BibleVerse;
use Illuminate\Database\Eloquent\Builder;

/**
 * Entrega o texto de uma referência a partir dos versículos importados.
 *
 * Devolve null quando a referência não é reconhecida ou quando o texto ainda
 * não foi importado (`php artisan bible:import`): as telas então mostram só a
 * referência, como sempre fizeram.
 */
final class Bible
{
    /**
     * @return array{label: string, version: string, credit: string, verses: list<array{chapter: int, verse: int, text: string}>}|null
     */
    public static function passage(?string $reference): ?array
    {
        $parsed = Reference::parse($reference);

        if ($parsed === null) {
            return null;
        }

        $verses = [];

        // Uma consulta por trecho, para manter a ordem em que foram escritos
        // ("Rm 8.28; Jo 14.1-6" não deve virar João antes de Romanos).
        foreach ($parsed->ranges as $range) {
            $rows = BibleVerse::query()
                ->where('book', $range->book->value)
                ->where(fn (Builder $query) => self::applyRange($query, $range))
                ->orderBy('chapter')
                ->orderBy('verse')
                ->get(['chapter', 'verse', 'text']);

            foreach ($rows as $row) {
                $verses[] = ['chapter' => $row->chapter, 'verse' => $row->verse, 'text' => $row->text];
            }
        }

        if ($verses === []) {
            return null;
        }

        return [
            'label' => $parsed->label(),
            'version' => (string) config('ebd.bible.version'),
            'credit' => (string) config('ebd.bible.credit'),
            'verses' => $verses,
        ];
    }

    /**
     * @param  Builder<BibleVerse>  $query
     */
    private static function applyRange(Builder $query, Range $range): void
    {
        if ($range->isWholeChapters()) {
            $query->whereBetween('chapter', [$range->fromChapter, $range->toChapter]);

            return;
        }

        $query
            ->where(fn (Builder $q) => $q
                ->where('chapter', '>', $range->fromChapter)
                ->orWhere(fn (Builder $q) => $q->where('chapter', $range->fromChapter)->where('verse', '>=', $range->fromVerse)))
            ->where(fn (Builder $q) => $q
                ->where('chapter', '<', $range->toChapter)
                ->orWhere(fn (Builder $q) => $q->where('chapter', $range->toChapter)->where('verse', '<=', $range->toVerse)));
    }
}
