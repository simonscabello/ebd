<?php

namespace App\Concerns;

use App\Enums\ContentAudience;
use App\Enums\LessonBlockKind;
use App\Enums\LessonVisibility;
use App\Enums\MaterialType;
use App\Enums\Weekday;
use Illuminate\Validation\Rule;

/**
 * Regras da lição e das partes dela (leituras, blocos, materiais com link),
 * compartilhadas pelos formulários do app, pelo lesson:import e pelo servidor
 * MCP. O prefixo serve para validar listas (ex.: "blocks.*.").
 */
trait LessonValidationRules
{
    /**
     * Campos de conteúdo da lição. Slug, unicidade do número e autores
     * dependem de quem edita e ficam em LessonRequest.
     *
     * @return array<string, mixed>
     */
    protected function lessonFieldRules(): array
    {
        return [
            'number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'bible_reference' => ['nullable', 'string', 'max:120'],
            'key_verse' => ['nullable', 'string', 'max:160'],
            'goal' => ['nullable', 'string', 'max:2000'],
            'content' => ['nullable', 'string', 'max:100000'],
            'visibility' => ['required', Rule::enum(LessonVisibility::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function lessonReadingRules(string $prefix = ''): array
    {
        return [
            "{$prefix}weekday" => ['nullable', Rule::enum(Weekday::class)],
            "{$prefix}reference" => ['required', 'string', 'max:160'],
            "{$prefix}notes" => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Sem público, o bloco usa o padrão do tipo (LessonBlockKind::defaultAudience).
     *
     * @return array<string, mixed>
     */
    protected function lessonBlockRules(string $prefix = ''): array
    {
        return [
            "{$prefix}kind" => ['required', Rule::enum(LessonBlockKind::class)],
            "{$prefix}audience" => ['nullable', Rule::enum(ContentAudience::class)],
            "{$prefix}title" => ['nullable', 'string', 'max:180'],
            "{$prefix}body" => ['required', 'string', 'max:60000'],
            "{$prefix}drip_weekday" => ['nullable', Rule::enum(Weekday::class)],
        ];
    }

    /**
     * Materiais que não dependem de arquivo enviado: arquivos só pelo app.
     *
     * @return array<string, mixed>
     */
    protected function lessonLinkMaterialRules(string $prefix = ''): array
    {
        return [
            "{$prefix}type" => ['required', Rule::in(array_map(fn (MaterialType $type) => $type->value, self::linkMaterialTypes()))],
            "{$prefix}audience" => ['nullable', Rule::enum(ContentAudience::class)],
            "{$prefix}title" => ['required', 'string', 'max:180'],
            "{$prefix}description" => ['nullable', 'string', 'max:2000'],
            "{$prefix}url" => ['nullable', "required_unless:{$prefix}type,reference", 'url:http,https', 'max:2048'],
        ];
    }

    /**
     * @return list<MaterialType>
     */
    protected static function linkMaterialTypes(): array
    {
        return [MaterialType::Reference, MaterialType::Link, MaterialType::Video, MaterialType::Audio];
    }

    /**
     * Nomes dos campos nas mensagens de erro.
     *
     * @return array<string, string>
     */
    protected function lessonAttributes(): array
    {
        return [
            'series_id' => 'série',
            'title' => 'título',
            'number' => 'número da lição',
            'summary' => 'resumo',
            'meeting_on' => 'domingo da aula',
            'key_verse' => 'versículo-chave',
            'goal' => 'alvo da lição',
            'bible_reference' => 'texto bíblico',
            'content' => 'conteúdo',
            'visibility' => 'visibilidade',
        ];
    }

    /**
     * Nomes dos campos de leituras, blocos e materiais (com o prefixo da lista, se houver).
     *
     * @return array<string, string>
     */
    protected function lessonPartAttributes(string $prefix = ''): array
    {
        $names = [
            'weekday' => 'dia',
            'reference' => 'leitura',
            'notes' => 'orientação',
            'kind' => 'tipo',
            'audience' => 'público',
            'body' => 'conteúdo',
            'drip_weekday' => 'dia da semana',
            'type' => 'tipo',
            'description' => 'descrição',
            'url' => 'link',
        ];

        return collect($names)->mapWithKeys(fn (string $name, string $field) => ["{$prefix}{$field}" => $name])->all();
    }
}
