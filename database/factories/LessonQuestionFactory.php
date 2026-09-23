<?php

namespace Database\Factories;

use App\Enums\QuestionKind;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonQuestion>
 */
class LessonQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'body' => rtrim(fake()->sentence(10), '.').'?',
        ];
    }

    public function review(?string $answer = null): static
    {
        return $this->state([
            'kind' => QuestionKind::Review,
            'answer' => $answer ?? fake()->sentence(),
        ]);
    }
}
