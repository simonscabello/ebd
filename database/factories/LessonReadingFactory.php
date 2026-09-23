<?php

namespace Database\Factories;

use App\Enums\Weekday;
use App\Models\Lesson;
use App\Models\LessonReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonReading>
 */
class LessonReadingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'weekday' => fake()->randomElement(Weekday::cases()),
            'reference' => fake()->randomElement(['Salmo 23', 'Isaías 6:1-8', 'João 21:1-14', 'Mateus 4:18-22']),
            'notes' => null,
        ];
    }
}
