<?php

namespace App\Http\Resources;

use App\Models\Lesson;
use App\Support\ChurchCalendar;
use App\Support\Markdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representação de uma lição para leitura (página da lição, home, biblioteca).
 * Relações só entram quando carregadas; notas do professor só com permissão.
 *
 * @mixin Lesson
 */
class LessonResource extends JsonResource
{
    private bool $withContent = false;

    private bool $withTeacherNotes = false;

    /** Inclui o conteúdo completo em HTML (página da lição / Modo Domingo). */
    public function withContent(): static
    {
        $this->withContent = true;

        return $this;
    }

    public function withTeacherNotes(bool $allowed): static
    {
        $this->withTeacherNotes = $allowed;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $date = $this->scheduled_for;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'url' => route('lessons.show', $this->slug),
            'summary' => $this->summary,
            'scheduled_for' => $date?->toDateString(),
            'date_label' => $date ? ChurchCalendar::formatLong($date) : null,
            'date_short' => $date ? ChurchCalendar::formatShort($date) : null,
            'days_until' => $date ? ChurchCalendar::daysUntil($date) : null,
            'bible_reference' => $this->bible_reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'visibility' => $this->visibility->value,
            'is_public' => $this->isPublic(),
            'classroom' => ClassroomResource::make($this->whenLoaded('classroom')),
            'series' => SeriesResource::make($this->whenLoaded('series')),
            'authors' => $this->whenLoaded('authors', fn () => $this->authors->pluck('name')->all()),
            'materials' => LessonMaterialResource::collection($this->whenLoaded('materials')),
            'readings' => LessonReadingResource::collection($this->whenLoaded('readings')),
            'questions' => LessonQuestionResource::collection($this->whenLoaded('questions')),
            'questions_count' => $this->whenCounted('questions'),
            'materials_count' => $this->whenCounted('materials'),
            $this->mergeWhen($this->withContent, fn () => [
                'bible_text' => $this->bible_text,
                'content_html' => Markdown::toHtml($this->content),
                'topics' => Markdown::headings($this->content),
            ]),
            $this->mergeWhen($this->withTeacherNotes, fn () => [
                'teacher_notes_html' => Markdown::toHtml($this->teacher_notes),
            ]),
            'headline' => $this->when(isset($this->resource->headline), fn () => $this->resource->headline),
        ];
    }
}
