<?php

namespace App\Actions\Classrooms;

use App\Models\Classroom;
use App\Models\Program;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CreateClassroom
{
    /**
     * @param  array<string, mixed>  $data  dados validados (name, description, is_active, position)
     */
    public function handle(array $data, Program $program): Classroom
    {
        $classroom = new Classroom(Arr::only($data, ['name', 'description', 'is_active', 'position', 'meeting_weekday']));
        $classroom->church_id = $program->church_id;
        $classroom->program_id = $program->id;
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
