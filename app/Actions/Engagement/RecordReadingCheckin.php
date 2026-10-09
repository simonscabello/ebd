<?php

namespace App\Actions\Engagement;

use App\Enums\Badge;
use App\Enums\Weekday;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\User;
use App\Support\ChurchCalendar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Marca como lida a leitura de um dia do plano da lição. Qualquer dia pode
 * ser marcado a qualquer momento (adiantar ou pôr em dia); read_on guarda
 * quando a pessoa marcou, no fuso da igreja.
 */
class RecordReadingCheckin
{
    public function __construct(
        private readonly AwardBadges $awardBadges,
    ) {}

    /**
     * @return list<Badge> selos conquistados com este check-in
     */
    public function handle(User $user, Lesson $lesson, Weekday $weekday, ?LessonReading $reading = null): array
    {
        if (! $user->isMemberOf($lesson->classroom_id) || Gate::forUser($user)->denies('view', $lesson)) {
            abort(403);
        }

        if ($reading !== null && $reading->lesson_id !== $lesson->id) {
            throw ValidationException::withMessages(['reading_id' => 'Esta leitura não é desta lição.']);
        }

        DB::table('reading_checkins')->insertOrIgnore([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'lesson_reading_id' => $reading?->id,
            'weekday' => ($reading->weekday ?? $weekday)->value,
            'read_on' => ChurchCalendar::today()->toDateString(),
            'created_at' => now(),
        ]);

        return $this->awardBadges->handle($user, $lesson->series);
    }
}
