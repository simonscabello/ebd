<?php

namespace Database\Factories;

use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassMeeting>
 */
class ClassMeetingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'lesson_id' => null,
            'held_on' => now()->next('Sunday')->toDateString(),
            'status' => MeetingStatus::Planned,
        ];
    }

    public function on(string $date): static
    {
        return $this->state(['held_on' => $date]);
    }

    public function forLesson(Lesson $lesson): static
    {
        return $this->state([
            'lesson_id' => $lesson->id,
            'classroom_id' => $lesson->classroom_id,
        ]);
    }

    public function held(): static
    {
        return $this->state(['status' => MeetingStatus::Held]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => MeetingStatus::Cancelled, 'lesson_id' => null]);
    }
}
