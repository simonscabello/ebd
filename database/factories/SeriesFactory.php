<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\Series;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Series>
 */
class SeriesFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->randomElement([
            'Evangelho de João', 'Filipenses', 'Santidade de Deus', 'Parábolas de Jesus',
            'Sermão do Monte', 'Vida de Davi', 'Atos dos Apóstolos', 'Frutos do Espírito',
        ]);

        return [
            'classroom_id' => Classroom::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'starts_on' => null,
            'ends_on' => null,
        ];
    }
}
