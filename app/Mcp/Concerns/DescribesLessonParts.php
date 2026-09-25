<?php

namespace App\Mcp\Concerns;

use App\Enums\ContentAudience;
use App\Enums\LessonBlockKind;
use App\Enums\MaterialType;
use App\Enums\Weekday;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * Schemas das partes da lição (leitura, bloco, material com link), usados
 * pelas ferramentas de rascunho, com as opções explicadas para o agente.
 */
trait DescribesLessonParts
{
    /**
     * @return array<string, Type>
     */
    protected function readingProperties(JsonSchema $schema): array
    {
        return [
            'weekday' => $schema->integer()->enum(Weekday::class)->description('Dia da leitura: 1=segunda, 2=terça, 3=quarta, 4=quinta, 5=sexta, 6=sábado, 7=domingo. Sem dia = leitura extra.'),
            'reference' => $schema->string()->description('Referência bíblica, ex.: "Gênesis 12.1-9".')->required(),
            'notes' => $schema->string()->description('Orientação curta para a leitura.'),
        ];
    }

    /**
     * @return array<string, Type>
     */
    protected function blockProperties(JsonSchema $schema): array
    {
        $kinds = collect(LessonBlockKind::cases())
            ->map(fn (LessonBlockKind $k) => "{$k->value} ({$k->label()}, padrão: {$k->defaultAudience()->value})")
            ->implode('; ');

        return [
            'kind' => $schema->string()->enum(LessonBlockKind::class)->description("Tipo do bloco: {$kinds}.")->required(),
            'audience' => $schema->string()->enum(ContentAudience::class)->description('teacher (só o professor vê) ou student. Sem valor, usa o padrão do tipo.'),
            'title' => $schema->string()->description('Título opcional do bloco.'),
            'body' => $schema->string()->description('Texto em Markdown.')->required(),
            'drip_weekday' => $schema->integer()->enum(Weekday::class)->description('Liberar o bloco para os alunos neste dia da semana (1=segunda ... 7=domingo). Só para blocos de aluno.'),
        ];
    }

    /**
     * @return array<string, Type>
     */
    protected function materialProperties(JsonSchema $schema): array
    {
        $types = collect(self::linkMaterialTypes())->map(fn (MaterialType $t) => "{$t->value} ({$t->label()})")->implode(', ');

        return [
            'type' => $schema->string()->enum(array_map(fn (MaterialType $t) => $t->value, self::linkMaterialTypes()))->description("Tipo: {$types}. PDFs e arquivos são enviados pelo app.")->required(),
            'title' => $schema->string()->description('Título do material.')->required(),
            'url' => $schema->string()->description('Link (http/https). Obrigatório, exceto para reference.'),
            'description' => $schema->string()->description('Descrição curta.'),
            'audience' => $schema->string()->enum(ContentAudience::class)->description('student (padrão) ou teacher.'),
        ];
    }
}
