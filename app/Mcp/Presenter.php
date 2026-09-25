<?php

namespace App\Mcp;

use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\LessonMaterial;
use App\Models\LessonReading;
use App\Models\Series;
use App\Models\User;
use App\Support\ChurchCalendar;

/**
 * Formato das respostas do servidor MCP: dados enxutos, com textos em
 * Markdown (não HTML) e o link da tela correspondente no app, para a pessoa
 * conferir ou terminar o que o agente não faz (publicar, enviar arquivo).
 */
final class Presenter
{
    /**
     * @return array<string, mixed>
     */
    public static function classroom(Classroom $classroom): array
    {
        return [
            'id' => $classroom->id,
            'slug' => $classroom->slug,
            'name' => $classroom->name,
            'is_active' => $classroom->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function series(Series $series): array
    {
        return [
            'id' => $series->id,
            'slug' => $series->slug,
            'title' => $series->title,
            'starts_on' => $series->starts_on?->toDateString(),
            'ends_on' => $series->ends_on?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function lessonSummary(Lesson $lesson): array
    {
        return [
            'id' => $lesson->id,
            'number' => $lesson->number,
            'title' => $lesson->title,
            'display_title' => $lesson->displayTitle(),
            'status' => $lesson->status->value,
            'series' => $lesson->relationLoaded('series') && $lesson->series ? $lesson->series->slug : null,
            'classroom' => $lesson->relationLoaded('classroom') ? $lesson->classroom->slug : null,
            'bible_reference' => $lesson->bible_reference,
            'scheduled_for' => $lesson->scheduled_for?->toDateString(),
            'edit_url' => route('admin.lessons.edit', $lesson),
        ];
    }

    /**
     * Lição completa, com o conteúdo do professor. Só para quem gerencia a classe.
     *
     * @return array<string, mixed>
     */
    public static function lesson(Lesson $lesson): array
    {
        return [
            ...self::lessonSummary($lesson),
            'slug' => $lesson->slug,
            'summary' => $lesson->summary,
            'key_verse' => $lesson->key_verse,
            'goal' => $lesson->goal,
            'visibility' => $lesson->visibility->value,
            'content' => $lesson->content,
            'published_at' => $lesson->published_at?->toIso8601String(),
            'public_url' => $lesson->status->isVisible() ? route('lessons.show', $lesson->slug) : null,
            'authors' => $lesson->authors->pluck('name')->all(),
            'readings' => $lesson->readings->map(self::reading(...))->all(),
            'blocks' => $lesson->blocks->map(self::block(...))->all(),
            'materials' => $lesson->materials->map(self::material(...))->all(),
            'meetings' => $lesson->meetings->map(fn (ClassMeeting $m) => [
                'id' => $m->id,
                'held_on' => $m->held_on->toDateString(),
                'status' => $m->status->value,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function reading(LessonReading $reading): array
    {
        return [
            'id' => $reading->id,
            'weekday' => $reading->weekday?->value,
            'weekday_label' => $reading->weekday?->label(),
            'reference' => $reading->reference,
            'notes' => $reading->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function block(LessonBlock $block): array
    {
        return [
            'id' => $block->id,
            'kind' => $block->kind->value,
            'audience' => $block->audience->value,
            'title' => $block->title,
            'body' => $block->body,
            'drip_weekday' => $block->drip_weekday?->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function material(LessonMaterial $material): array
    {
        return [
            'id' => $material->id,
            'type' => $material->type->value,
            'audience' => $material->audience->value,
            'title' => $material->title,
            'description' => $material->description,
            'url' => $material->hasFile() ? null : $material->url,
            'file_name' => $material->hasFile() ? $material->original_name : null,
            'is_primary' => $material->is_primary,
        ];
    }

    /**
     * @param  array<int, string>|null  $present  nomes dos presentes, quando a chamada foi pedida
     * @return array<string, mixed>
     */
    public static function meeting(ClassMeeting $meeting, ?array $present = null): array
    {
        return array_filter([
            'id' => $meeting->id,
            'held_on' => $meeting->held_on->toDateString(),
            'date_label' => ChurchCalendar::formatLong($meeting->held_on),
            'status' => $meeting->status->value,
            'title' => $meeting->title,
            'lesson' => $meeting->relationLoaded('lesson') && $meeting->lesson ? [
                'id' => $meeting->lesson->id,
                'display_title' => $meeting->lesson->displayTitle(),
                'status' => $meeting->lesson->status->value,
            ] : null,
            'attendance_taken' => $meeting->hasAttendance(),
            'visitors_count' => $meeting->visitors_count,
            'notes' => $meeting->notes,
            'present' => $present,
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function student(User $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'phone' => $student->phone,
            'email' => $student->email,
            'is_managed' => $student->isManaged(),
        ];
    }
}
