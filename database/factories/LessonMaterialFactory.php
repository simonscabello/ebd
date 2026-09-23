<?php

namespace Database\Factories;

use App\Enums\MaterialType;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonMaterial>
 */
class LessonMaterialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'type' => MaterialType::Link,
            'title' => fake()->sentence(3),
            'description' => null,
            'url' => fake()->url(),
            'is_primary' => false,
        ];
    }

    /**
     * Material com arquivo já gravado no disco configurado.
     */
    public function withFile(string $path, string $mime = 'application/pdf', ?string $disk = null): static
    {
        return $this->state([
            'type' => MaterialType::Pdf,
            'url' => null,
            'disk' => $disk ?? config('ebd.materials.disk'),
            'path' => $path,
            'original_name' => basename($path),
            'mime_type' => $mime,
            'size_bytes' => 1024,
        ]);
    }
}
