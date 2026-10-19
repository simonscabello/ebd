<?php

namespace App\Http\Resources;

use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Classroom
 */
class ClassroomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'meeting_weekday' => $this->meeting_weekday->value,
            'meeting_weekday_label' => $this->meeting_weekday->label(),
            'meeting_day' => $this->meeting_weekday->lowerLabel(),
            'meeting_name' => $this->meeting_weekday->meetingName(),
            'meeting_name_plural' => $this->meeting_weekday->meetingNamePlural(),
        ];
    }
}
