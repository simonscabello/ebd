<?php

namespace App\Console\Commands;

use App\Enums\BibleBook;
use App\Models\BibleVerse;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Importa o texto bíblico de um JSON local para a tabela bible_verses.
 *
 * Formato esperado (o mesmo dos JSON de bíblia mais comuns): uma lista com os
 * 66 livros na ordem canônica, cada um com `name` e `chapters`, onde cada
 * capítulo é uma lista de versículos em texto.
 *
 * O arquivo nunca vai para o repositório nem para o servidor: o comando roda no
 * computador de quem tem o arquivo, apontando para o banco desejado. Por isso
 * mostra o banco de destino e pede confirmação. É idempotente (upsert).
 */
#[Signature('bible:import
    {path : Caminho do JSON com o texto bíblico}
    {--fresh : Apaga os versículos existentes antes de importar}
    {--force : Não pede confirmação (uso em scripts)}')]
#[Description('Importa o texto bíblico (JSON) para a tabela bible_verses')]
class ImportBible extends Command
{
    private const int CHUNK = 1000;

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->components->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        $books = json_decode((string) file_get_contents($path), true);
        $error = $this->validate($books);

        if ($error !== null) {
            $this->components->error($error);

            return self::FAILURE;
        }

        /** @var list<array{name: string, chapters: list<list<string>>}> $books */
        $connection = DB::connection();
        $target = sprintf(
            '%s@%s/%s',
            (string) $connection->getConfig('username'),
            (string) $connection->getConfig('host'),
            $connection->getDatabaseName(),
        );

        $total = array_sum(array_map(fn (array $book) => array_sum(array_map('count', $book['chapters'])), $books));

        $this->components->info(sprintf('%s versículos em %s. Destino: %s (%s).', number_format($total, 0, ',', '.'), basename($path), $target, config('ebd.bible.version')));

        if (! $this->option('force') && ! $this->confirm('Importar para este banco?')) {
            $this->components->warn('Importação cancelada.');

            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::transaction(function () use ($books, $bar): void {
            if ($this->option('fresh')) {
                BibleVerse::query()->delete();
            }

            $rows = [];

            foreach ($books as $index => $book) {
                $number = BibleBook::from($index + 1)->value;

                foreach ($book['chapters'] as $chapterIndex => $verses) {
                    foreach ($verses as $verseIndex => $text) {
                        $rows[] = [
                            'book' => $number,
                            'chapter' => $chapterIndex + 1,
                            'verse' => $verseIndex + 1,
                            'text' => self::clean($text),
                        ];

                        if (count($rows) === self::CHUNK) {
                            $this->flush($rows, $bar);
                        }
                    }
                }
            }

            $this->flush($rows, $bar);
        });

        $bar->finish();
        $this->newLine(2);

        $this->components->info(sprintf('Pronto: %s versículos no banco.', number_format(BibleVerse::query()->count(), 0, ',', '.')));

        return self::SUCCESS;
    }

    /**
     * @param  list<array{book: int, chapter: int, verse: int, text: string}>  $rows
     */
    private function flush(array &$rows, ProgressBar $bar): void
    {
        if ($rows === []) {
            return;
        }

        BibleVerse::query()->upsert($rows, ['book', 'chapter', 'verse'], ['text']);
        $bar->advance(count($rows));
        $rows = [];
    }

    /**
     * Tira o espaço antes da pontuação que a NAA deixa depois das palavras em
     * versalete na edição impressa ("o Senhor , porém" → "o Senhor, porém").
     */
    private static function clean(string $text): string
    {
        return (string) preg_replace('/\s+([,;:.!?])/u', '$1', trim($text));
    }

    /**
     * Confere a estrutura e se a ordem dos livros bate com a canônica, para o
     * texto nunca ser gravado no livro errado.
     */
    private function validate(mixed $books): ?string
    {
        if (! is_array($books) || ! array_is_list($books) || count($books) !== 66) {
            return 'O JSON precisa ser uma lista com os 66 livros da Bíblia na ordem canônica.';
        }

        foreach ($books as $index => $book) {
            $expected = BibleBook::from($index + 1);

            if (! is_array($book) || ! is_string($book['name'] ?? null) || ! is_array($book['chapters'] ?? null)) {
                return sprintf('Livro %d: esperado um objeto com "name" e "chapters".', $index + 1);
            }

            if (BibleBook::fromName($book['name']) !== $expected) {
                return sprintf('Livro %d: "%s" não corresponde a %s.', $index + 1, $book['name'], $expected->label());
            }

            foreach ($book['chapters'] as $chapterIndex => $verses) {
                if (! is_array($verses) || $verses === [] || array_filter($verses, fn ($v) => ! is_string($v) || trim($v) === '') !== []) {
                    return sprintf('%s %d: capítulo vazio ou com versículo inválido.', $expected->label(), $chapterIndex + 1);
                }
            }
        }

        return null;
    }
}
