<?php

namespace Tests\Unit;

use App\Support\Audio\Mp3;
use App\Support\Audio\StudyNarration;
use App\Support\Bible\Reference;
use PHPUnit\Framework\TestCase;

class StudyNarrationTest extends TestCase
{
    public function test_markdown_becomes_plain_text_with_headings_apart(): void
    {
        $narration = StudyNarration::make('Lição 3 — O sinal de Jonas', implode("\n\n", [
            '## Introdução',
            'Os fariseus pedem um **sinal**, cf. v. 38. Leia [o texto](https://exemplo.com) com `atenção`.',
            '- Primeiro ponto',
            '- Segundo ponto!',
            '> Uma citação',
        ]));

        $this->assertSame(implode("\n\n", [
            'Lição 3 — O sinal de Jonas.',
            'Introdução.',
            'Os fariseus pedem um sinal, conforme versículo 38. Leia o texto com atenção.',
            'Primeiro ponto.',
            'Segundo ponto!',
            'Uma citação.',
        ]), $narration->text());
    }

    public function test_verse_citations_are_left_out_and_references_in_the_sentence_are_read_in_full(): void
    {
        $text = StudyNarration::make(null, implode("\n\n", [
            'Os fariseus pedem um sinal (Mt 12.38-40; Jo 3.16,18). Ele lembra Jonas (cf. Jn 1.17).',
            'No v. 40, Jesus fala do ventre do peixe (v. 40). Leia também 1 Co 13.1 e Salmo 23, cf. Jo 2.19.',
            'Gn 1.1-2.3.',
            'O Salmo 23 consola.',
        ]))->text();

        $this->assertSame(implode("\n\n", [
            'Os fariseus pedem um sinal. Ele lembra Jonas.',
            'No versículo 40, Jesus fala do ventre do peixe. Leia também Primeira Coríntios, capítulo 13, versículo 1 e Salmo 23.',
            'Gênesis, capítulo 1, versículo 1, até o capítulo 2, versículo 3.',
            'O Salmo 23 consola.',
        ]), $text);
        $this->assertSame('Segundo Reis, capítulos 2 a 4', StudyNarration::speakReference(Reference::parse('2Rs 2-4')));
    }

    public function test_chunks_respect_the_limit_and_prefer_section_breaks(): void
    {
        $paragraph = str_repeat('Palavra de estudo. ', 5);
        $narration = StudyNarration::make(null, "## Um\n\n{$paragraph}\n\n{$paragraph}\n\n## Dois\n\n{$paragraph}\n\n".str_repeat('Frase longa demais. ', 20));

        $chunks = array_column($narration->chunks(200), 'text');

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(200, mb_strlen($chunk));
        }
        $this->assertStringStartsWith('Um.', $chunks[0]);
        $this->assertStringStartsWith('Dois.', $chunks[1]);
        $this->assertSame(
            preg_replace('/\s+/', ' ', $narration->text()),
            preg_replace('/\s+/', ' ', implode(' ', $chunks)),
        );
    }

    public function test_each_main_topic_is_a_part_for_the_next_narrator(): void
    {
        $narration = StudyNarration::make('Lição 17 — Milagres', implode("\n\n", [
            'Introdução da lição.',
            '## I. O propósito', '### 1. Autenticar', 'Texto um.', '### 2. Glorificar', 'Texto dois.',
            '## II. A distorção', 'Texto três.',
            '## Conclusão', 'Fim.',
        ]));

        $this->assertSame([
            [0, 'Lição 17 — Milagres.'], [0, 'Introdução da lição.'],
            [1, 'I. O propósito.'], [1, '1. Autenticar.'], [1, 'Texto um.'], [1, '2. Glorificar.'], [1, 'Texto dois.'],
            [2, 'II. A distorção.'], [2, 'Texto três.'],
            [3, 'Conclusão.'], [3, 'Fim.'],
        ], array_merge(...array_map(
            fn (array $blocks, int $part) => array_map(fn (array $block) => [$part, $block['text']], $blocks),
            $narration->parts(),
            array_keys($narration->parts()),
        )));

        // Um trecho nunca junta duas partes, mesmo cabendo no limite.
        $this->assertSame([0, 1, 2, 3], array_column($narration->chunks(3500), 'part'));
    }

    public function test_title_alone_goes_with_the_first_topic_and_studies_without_topics_are_one_part(): void
    {
        $withTopics = StudyNarration::make('Título', "## Um\n\nA.\n\n## Dois\n\nB.");
        $this->assertSame(["Título.\n\nUm.\n\nA.", "Dois.\n\nB."], array_column($withTopics->chunks(3500), 'text'));

        $single = StudyNarration::make('Título', "Parágrafo.\n\n## Só um tópico\n\nTexto.");
        $this->assertCount(1, $single->parts());
    }

    public function test_mp3_parts_are_joined_without_tags_and_timed_by_frames(): void
    {
        $frames = str_repeat("\xFF\xF3\xC4\xC4".str_repeat("\0", 380), 125); // 3 s
        $tagged = "ID3\x04\x00\x00\x00\x00\x00\x05abcde".$frames;

        $joined = Mp3::concat([$frames, $tagged]);

        $this->assertSame($frames.$frames, $joined);
        $this->assertSame(6, Mp3::duration($joined));
        $this->assertNull(Mp3::duration('não é mp3'));
    }
}
