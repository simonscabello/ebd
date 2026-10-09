<?php

namespace Tests\Unit;

use App\Enums\BibleBook;
use App\Support\Bible\Reference;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BibleReferenceTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function references(): array
    {
        return [
            'dois pontos' => ['Lucas 5:12-16', 'Lucas 5.12-16'],
            'ponto e travessão' => ['Lc 5.1–11', 'Lucas 5.1-11'],
            'capítulo inteiro' => ['Sl 23', 'Salmos 23'],
            'capítulos inteiros' => ['Gênesis 1-3', 'Gênesis 1-3'],
            'entre capítulos' => ['Gn 1.1-2.3', 'Gênesis 1.1-2.3'],
            'dois trechos do mesmo livro' => ['Mt 5.3-12; 6.9-13', 'Mateus 5.3-12; 6.9-13'],
            'lista de versículos' => ['Jo 3.16,18', 'João 3.16; 3.18'],
            'lista de intervalos' => ['Mt 5.3-5,9-11', 'Mateus 5.3-5; 5.9-11'],
            'livros diferentes' => ['Rm 8.28-30; Jo 14.1-6', 'Romanos 8.28-30; João 14.1-6'],
            'livro numerado com espaço' => ['1 Coríntios 13', '1Coríntios 13'],
            'livro numerado abreviado' => ['1Co 13.4-7', '1Coríntios 13.4-7'],
            'abreviação com ponto' => ['Lc. 5.1', 'Lucas 5.1'],
            'Jó não é João' => ['Jó 1.21', 'Jó 1.21'],
            'sem acento' => ['Joao 3.16', 'João 3.16'],
            'espaços extras' => ['  Jo  3 . 16 ', 'João 3.16'],
            'nome completo com acento' => ['Êxodo 20.1-17', 'Êxodo 20.1-17'],
        ];
    }

    #[DataProvider('references')]
    public function test_it_understands_the_formats_teachers_write(string $input, string $label): void
    {
        $reference = Reference::parse($input);

        $this->assertNotNull($reference, "Não reconheceu: {$input}");
        $this->assertSame($label, $reference->label());
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function unrecognized(): array
    {
        return [
            'vazio' => [''],
            'nulo' => [null],
            'sem livro' => ['5.12-16'],
            'livro desconhecido' => ['Enoque 1.1'],
            'palavras no meio' => ['Lucas capítulo 5'],
            'intervalo invertido' => ['Lc 5.10-2'],
            'capítulos invertidos' => ['Sl 5-2'],
            'só texto' => ['Leia com atenção'],
        ];
    }

    #[DataProvider('unrecognized')]
    public function test_it_returns_null_for_what_it_does_not_understand(?string $input): void
    {
        $this->assertNull(Reference::parse($input));
    }

    public function test_ranges_carry_the_book_and_limits(): void
    {
        $ranges = Reference::parse('Jo 3.16; Sl 23')?->ranges ?? [];

        $this->assertCount(2, $ranges);
        $this->assertSame(BibleBook::John, $ranges[0]->book);
        $this->assertSame([3, 16, 3, 16], [$ranges[0]->fromChapter, $ranges[0]->fromVerse, $ranges[0]->toChapter, $ranges[0]->toVerse]);
        $this->assertSame(BibleBook::Psalms, $ranges[1]->book);
        $this->assertTrue($ranges[1]->isWholeChapters());
    }

    public function test_every_book_is_found_by_its_own_name_and_aliases(): void
    {
        foreach (BibleBook::cases() as $book) {
            $this->assertSame($book, BibleBook::fromName($book->label()), $book->label());

            foreach ($book->aliases() as $alias) {
                $this->assertSame($book, BibleBook::fromName($alias), "{$alias} deveria ser {$book->label()}");
            }
        }
    }
}
