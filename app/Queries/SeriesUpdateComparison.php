<?php

namespace App\Queries;

use App\Models\Series;
use App\Models\SeriesVersion;
use App\Support\TextDiff;

/**
 * O que mudou no estudo oficial entre a versão que a cópia conhece e a
 * última: lições novas, removidas e alteradas, com o diff de cada texto.
 * Só mostra; nada é aplicado na cópia.
 */
class SeriesUpdateComparison
{
    private const FIELDS = [
        'title' => 'Título',
        'summary' => 'Resumo',
        'bible_reference' => 'Texto bíblico',
        'key_verse' => 'Versículo-chave',
        'goal' => 'Objetivo',
        'content' => 'Estudo',
        'blocks' => 'Complementos',
        'readings' => 'Leituras da semana',
        'materials' => 'Materiais',
    ];

    /**
     * @return array{from: int, to: int, notes: list<string>, lessons: list<array{number: int|null, title: string, status: string, fields: list<array{name: string, label: string, diff: list<array{type: string, text: string}>}>}>}|null
     */
    public function for(Series $copy): ?array
    {
        $update = $copy->updateAvailable();

        if ($update === null) {
            return null;
        }

        $versions = SeriesVersion::query()
            ->where('series_id', $copy->source_series_id)
            ->whereIn('version', [$update['from'], $update['to']])
            ->get()
            ->keyBy('version');

        $notes = SeriesVersion::query()
            ->where('series_id', $copy->source_series_id)
            ->where('version', '>', $update['from'])
            ->where('version', '<=', $update['to'])
            ->whereNotNull('notes')
            ->orderBy('version')
            ->pluck('notes')
            ->all();

        $old = collect($versions->get($update['from'])->snapshot['lessons'] ?? [])->keyBy('source_lesson_id');
        $new = collect($versions->get($update['to'])->snapshot['lessons'] ?? [])->keyBy('source_lesson_id');

        $lessons = [];

        foreach ($new as $id => $lesson) {
            $before = $old->get($id);
            $fields = $this->fields($before, $lesson);

            if ($before === null || $fields !== []) {
                $lessons[] = [
                    'number' => $lesson['number'],
                    'title' => $lesson['title'],
                    'status' => $before === null ? 'added' : 'changed',
                    'fields' => $before === null ? [] : $fields,
                ];
            }
        }

        foreach ($old as $id => $lesson) {
            if (! $new->has($id)) {
                $lessons[] = ['number' => $lesson['number'], 'title' => $lesson['title'], 'status' => 'removed', 'fields' => []];
            }
        }

        return [
            'from' => $update['from'],
            'to' => $update['to'],
            'notes' => array_values(array_map('strval', $notes)),
            'lessons' => $lessons,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     * @return list<array{name: string, label: string, diff: list<array{type: string, text: string}>}>
     */
    private function fields(?array $before, array $after): array
    {
        if ($before === null) {
            return [];
        }

        $fields = [];

        foreach (self::FIELDS as $name => $label) {
            $old = $this->text($name, $before);
            $new = $this->text($name, $after);

            if (TextDiff::changed($old, $new)) {
                $fields[] = ['name' => $name, 'label' => $label, 'diff' => TextDiff::lines($old, $new)];
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $lesson
     */
    private function text(string $field, array $lesson): ?string
    {
        return match ($field) {
            'blocks' => implode("\n\n", array_map(fn (array $b) => trim(($b['title'] ? $b['title']."\n" : '').$b['body']), $lesson['blocks'])),
            'readings' => implode("\n", array_map(fn (array $r) => trim($r['reference'].($r['notes'] ? " — {$r['notes']}" : '')), $lesson['readings'])),
            'materials' => implode("\n", array_map(fn (array $m) => trim($m['title'].($m['url'] ? " ({$m['url']})" : '')), $lesson['materials'])),
            default => $lesson[$field] === null ? null : (string) $lesson[$field],
        };
    }
}
