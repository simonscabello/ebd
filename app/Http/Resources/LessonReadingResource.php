<?php

namespace App\Http\Resources;

use App\Models\LessonReading;
use App\Support\Bible\Bible;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LessonReading
 */
class LessonReadingResource extends JsonResource
{
    /**
     * Dia do plano que cai hoje na semana de leitura da lição (ver
     * Lesson::readingWeekdayToday). Sem ele, nenhuma leitura é "de hoje".
     */
    public ?int $todayWeekday = null;

    /**
     * @param  iterable<int, LessonReading>  $readings
     */
    public static function forWeek(iterable $readings, ?int $todayWeekday): AnonymousResourceCollection
    {
        $collection = static::collection($readings);
        $collection->collection->each(fn (self $reading) => $reading->todayWeekday = $todayWeekday);

        return $collection;
    }

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
            'is_today' => $this->todayWeekday !== null && $this->weekday?->value === $this->todayWeekday,
            'reference' => $this->reference,
            'passage' => Bible::passage($this->reference),
            'notes' => $this->notes,
            'position' => $this->position,
        ];
    }
}
