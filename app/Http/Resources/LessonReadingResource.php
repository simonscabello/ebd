<?php

namespace App\Http\Resources;

use App\Models\LessonReading;
use App\Support\ChurchCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LessonReading
 */
class LessonReadingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'weekday' => $this->weekday?->value,
            'weekday_label' => $this->weekday?->label(),
            'weekday_short' => $this->weekday?->shortLabel(),
            'is_today' => $this->weekday?->value === ChurchCalendar::today()->dayOfWeekIso,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'position' => $this->position,
        ];
    }
}
