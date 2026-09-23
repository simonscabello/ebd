<?php

namespace Database\Factories;

use App\Enums\LessonStatus;
use App\Enums\LessonVisibility;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    public function definition(): array
    {
        $title = Str::title(rtrim(fake()->sentence(4), '.'));

        return [
            'classroom_id' => Classroom::factory(),
            'series_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'summary' => fake()->paragraph(),
            'scheduled_for' => now()->next('Sunday')->toDateString(),
            'bible_reference' => 'Lucas 5:1-11',
            'bible_text' => null,
            'content' => "## Introdução\n\n".fake()->paragraph()."\n\n".fake()->paragraph(),
            'teacher_notes' => null,
            'status' => LessonStatus::Draft,
            'visibility' => LessonVisibility::Public,
        ];
    }

    public function forSeries(Series $series): static
    {
        return $this->state([
            'series_id' => $series->id,
            'classroom_id' => $series->classroom_id,
        ]);
    }

    public function published(): static
    {
        return $this->state([
            'status' => LessonStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => LessonStatus::Completed,
            'published_at' => now()->subWeek(),
            'completed_at' => now(),
        ]);
    }

    public function membersOnly(): static
    {
        return $this->state(['visibility' => LessonVisibility::Members]);
    }

    public function on(string $date): static
    {
        return $this->state(['scheduled_for' => $date]);
    }
}
