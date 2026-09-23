<?php

namespace App\Actions\Series;

use App\Models\Classroom;
use App\Models\Series;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CreateSeries
{
    /**
     * @param  array<string, mixed>  $data  dados validados (title, description, starts_on, ends_on)
     */
    public function handle(Classroom $classroom, array $data): Series
    {
        $series = new Series(Arr::only($data, ['title', 'description', 'starts_on', 'ends_on']));
        $series->classroom_id = $classroom->id;
        $series->slug = $this->uniqueSlug($classroom, $series->title);
        $series->save();

        return $series;
    }

    private function uniqueSlug(Classroom $classroom, string $title): string
    {
        $base = Str::of(Str::slug($title))->limit(160, '')->trim('-')->value() ?: 'serie';
        $slug = $base;
        $suffix = 2;

        while (Series::query()->where('classroom_id', $classroom->id)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
