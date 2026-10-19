<?php

namespace App\Http\Resources;

use App\Models\Series;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Series
 */
class SeriesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'classroom_id' => $this->classroom_id,
            'classroom' => ClassroomResource::make($this->whenLoaded('classroom')),
            'lessons_count' => $this->whenCounted('lessons'),
            // Só nas listas que já trazem a última versão da origem (gestão de séries).
            'update_available' => $this->when($this->resource->hasAttribute('latest_source_version'), fn () => $this->resource->updateAvailable()),
        ];
    }
}
