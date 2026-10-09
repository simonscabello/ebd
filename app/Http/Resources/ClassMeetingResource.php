<?php

namespace App\Http\Resources;

use App\Models\ClassMeeting;
use App\Support\ChurchCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Encontro (domingo) de uma classe. Notas do encontro só com withNotes().
 *
 * @mixin ClassMeeting
 */
class ClassMeetingResource extends JsonResource
{
    private bool $withNotes = false;

    public function withNotes(bool $allowed = true): static
    {
        $this->withNotes = $allowed;

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'held_on' => $this->held_on->toDateString(),
            'date_label' => ChurchCalendar::formatLong($this->held_on),
            'date_short' => ChurchCalendar::formatShort($this->held_on),
            'days_until' => ChurchCalendar::daysUntil($this->held_on),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'title' => $this->title,
            'lesson_id' => $this->lesson_id,
            'lesson' => $this->whenLoaded('lesson', fn () => $this->lesson ? [
                'id' => $this->lesson->id,
                'title' => $this->lesson->title,
                'display_title' => $this->lesson->displayTitle(),
                'number' => $this->lesson->number,
                'slug' => $this->lesson->slug,
                'status' => $this->lesson->status->value,
            ] : null),
            'has_attendance' => $this->attendance_taken_at !== null,
            'visitors_count' => $this->visitors_count,
            $this->mergeWhen($this->withNotes, fn () => [
                'notes' => $this->notes,
            ]),
        ];
    }
}
