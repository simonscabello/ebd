<?php

namespace Tests\Support;

use App\Enums\BibleBook;
use App\Models\BibleVerse;

/**
 * Texto bíblico de mentira para os testes: cada versículo diz onde está
 * ("Lucas 5.12"), o que torna as asserções legíveis.
 */
final class FakeBible
{
    /**
     * @param  array<int, array<int, int>>  $shape  livro => [capítulo => quantidade de versículos]
     */
    public static function seed(array $shape): void
    {
        $rows = [];

        foreach ($shape as $book => $chapters) {
            foreach ($chapters as $chapter => $count) {
                for ($verse = 1; $verse <= $count; $verse++) {
                    $rows[] = [
                        'book' => $book,
                        'chapter' => $chapter,
                        'verse' => $verse,
                        'text' => BibleBook::from($book)->label()." {$chapter}.{$verse}",
                    ];
                }
            }
        }

        BibleVerse::query()->insert($rows);
    }

    /**
     * Um JSON completo (66 livros, um versículo por capítulo) no formato aceito
     * por `bible:import`, gravado em um arquivo temporário.
     */
    public static function json(string $path, int $chaptersPerBook = 2): void
    {
        $books = [];

        foreach (BibleBook::cases() as $book) {
            $chapters = [];

            for ($chapter = 1; $chapter <= $chaptersPerBook; $chapter++) {
                $chapters[] = ["{$book->label()} {$chapter}.1", "{$book->label()} {$chapter}.2"];
            }

            $books[] = ['abbrev' => strtolower($book->name), 'name' => $book->label(), 'chapters' => $chapters];
        }

        file_put_contents($path, json_encode($books, JSON_UNESCAPED_UNICODE));
    }
}
