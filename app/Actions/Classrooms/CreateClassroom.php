<?php

namespace App\Actions\Classrooms;

use App\Models\Classroom;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CreateClassroom
{
    /**
     * @param  array{name: string, description?: string|null, is_active?: bool, position?: int}  $data
     */
    public function handle(array $data): Classroom
    {
        $classroom = new Classroom(Arr::only($data, ['name', 'description', 'is_active', 'position']));
        $classroom->slug = $this->uniqueSlug($data['name']);
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
