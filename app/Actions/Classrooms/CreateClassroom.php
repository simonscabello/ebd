<?php

namespace App\Actions\Classrooms;

use App\Models\Church;
use App\Models\Classroom;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CreateClassroom
{
    /**
     * @param  array<string, mixed>  $data  dados validados (name, description, is_active, position)
     */
    public function handle(array $data, Church $church): Classroom
    {
        $classroom = new Classroom(Arr::only($data, ['name', 'description', 'is_active', 'position']));
        $classroom->church_id = $church->id;
        $classroom->slug = $this->uniqueSlug((string) $classroom->name);
        $classroom->save();

        return $classroom;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'classe';
        $slug = $base;
        $suffix = 2;

        while (Classroom::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
