<?php

namespace App\Support\Library;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\LessonMaterial;
use App\Models\LessonReading;
use App\Models\Series;

/**
 * Retrato de uma série para a biblioteca oficial: só as lições publicadas,
 * em ordem, com blocos, leituras e materiais. Os arquivos de material ainda
 * apontam para o original; PublishSeriesVersion os copia para a versão.
 */
final class SeriesSnapshot
{
    /**
     * @return array{title: string, description: string|null, lessons: list<array<string, mixed>>}
     */
    public static function of(Series $series): array
    {
        $lessons = $series->lessons()
            ->where('status', LessonStatus::Published)
            ->with(['blocks', 'readings', 'materials'])
            ->orderByRaw('number is null, number')
            ->orderBy('id')
            ->get();

        return [
            'title' => $series->title,
            'description' => $series->description,
            'lessons' => array_values($lessons->map(fn (Lesson $lesson) => [
                'source_lesson_id' => $lesson->id,
                'number' => $lesson->number,
                'title' => $lesson->title,
                'summary' => $lesson->summary,
                'bible_reference' => $lesson->bible_reference,
                'key_verse' => $lesson->key_verse,
                'goal' => $lesson->goal,
                'content' => $lesson->content,
                'visibility' => $lesson->visibility->value,
                'blocks' => array_values($lesson->blocks->map(fn (LessonBlock $block) => [
                    'kind' => $block->kind->value,
                    'audience' => $block->audience->value,
                    'title' => $block->title,
                    'body' => $block->body,
                    'drip_weekday' => $block->drip_weekday?->value,
                    'position' => $block->position,
                ])->all()),
                'readings' => array_values($lesson->readings->map(fn (LessonReading $reading) => [
                    'weekday' => $reading->weekday?->value,
                    'reference' => $reading->reference,
                    'notes' => $reading->notes,
                    'position' => $reading->position,
                ])->all()),
                'materials' => array_values($lesson->materials->map(fn (LessonMaterial $material) => [
                    'type' => $material->type->value,
                    'audience' => $material->audience->value,
                    'title' => $material->title,
                    'description' => $material->description,
                    'url' => $material->url,
                    'is_primary' => $material->is_primary,
                    'position' => $material->position,
                    'file' => $material->path ? [
                        'disk' => $material->disk,
                        'path' => $material->path,
                        'original_name' => $material->original_name,
                        'mime_type' => $material->mime_type,
                        'size_bytes' => $material->size_bytes,
                    ] : null,
                ])->all()),
            ])->all()),
        ];
    }
}
