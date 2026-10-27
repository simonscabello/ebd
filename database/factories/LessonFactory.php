<?php

namespace Database\Factories;

use App\Enums\LessonStatus;
use App\Enums\LessonVisibility;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Support\ChurchCalendar;
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
            // A data vem dos encontros: use on() para criar o encontro junto.
            'scheduled_for' => null,
            'bible_reference' => 'Lucas 5:1-11',
            'content' => "## Introdução\n\n".fake()->paragraph()."\n\n".fake()->paragraph(),
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

    /**
     * Publicada e já estudada: os encontros dela ficam como realizados.
     * Use junto com on().
     */
    public function completed(): static
    {
        return $this->state([
            'status' => LessonStatus::Published,
            'published_at' => now()->subWeek(),
        ])->afterCreating(function (Lesson $lesson) {
            ClassMeeting::query()->where('lesson_id', $lesson->id)->update(['status' => MeetingStatus::Held]);
        });
    }

    public function membersOnly(): static
    {
        return $this->state(['visibility' => LessonVisibility::Members]);
    }

    /**
     * Coloca a lição no encontro (domingo) da data. Se a classe já tiver um
     * encontro nesse dia, ele é mantido. Encontros no passado nascem realizados
     * e a lição fica estudada; a do encontro futuro mais próximo vira a lição
     * atual da classe (como a migração que trouxe a lição atual fez).
     */
    public function on(string $date): static
    {
        return $this->state(['scheduled_for' => $date])->afterCreating(function (Lesson $lesson) use ($date) {
            $today = ChurchCalendar::today()->toDateString();

            ClassMeeting::query()->firstOrCreate(
                ['classroom_id' => $lesson->classroom_id, 'held_on' => $date],
                [
                    'lesson_id' => $lesson->id,
                    'status' => $date < $today ? MeetingStatus::Held : MeetingStatus::Planned,
                ],
            );

            $classroom = $lesson->classroom->refresh();

            if ($date < $today) {
                if ($classroom->current_lesson_id !== $lesson->id) {
                    $lesson->forceFill(['studied_at' => $lesson->studied_at ?? $date])->save();
                }

                return;
            }

            $currentOn = ClassMeeting::query()
                ->where('lesson_id', $classroom->current_lesson_id)
                ->whereDate('held_on', '>=', $today)
                ->min('held_on');

            if ($classroom->current_lesson_id === null || ($currentOn !== null && $date < substr((string) $currentOn, 0, 10))) {
                $lesson->forceFill(['studied_at' => null])->save();
                $classroom->forceFill([
                    'current_lesson_id' => $lesson->id,
                    'current_series_id' => $lesson->series_id ?? $classroom->current_series_id,
                    'current_lesson_started_at' => now(),
                ])->save();
            }
        });
    }

    public function number(int $number): static
    {
        return $this->state(['number' => $number]);
    }
}
