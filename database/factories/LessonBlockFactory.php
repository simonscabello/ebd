<?php

namespace Database\Factories;

use App\Enums\ContentAudience;
use App\Enums\LessonBlockKind;
use App\Models\Lesson;
use App\Models\LessonBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonBlock>
 */
class LessonBlockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'kind' => LessonBlockKind::Curiosity,
            'audience' => ContentAudience::Student,
            'title' => rtrim(fake()->sentence(4), '.'),
            'body' => fake()->paragraph(),
        ];
    }

    public function kind(LessonBlockKind $kind): static
    {
        return $this->state(['kind' => $kind, 'audience' => $kind->defaultAudience()]);
    }

    public function teacherOnly(): static
    {
        return $this->state(['kind' => LessonBlockKind::Roteiro, 'audience' => ContentAudience::Teacher, 'drip_weekday' => null]);
    }

    public function drip(int $weekday): static
    {
        return $this->state(['drip_weekday' => $weekday]);
    }
}
