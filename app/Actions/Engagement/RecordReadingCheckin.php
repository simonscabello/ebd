<?php

namespace App\Actions\Engagement;

use App\Enums\Badge;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\User;
use App\Support\ChurchCalendar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * "Li hoje" (ou "li ontem", para quem esqueceu de marcar). A data vem sempre
 * do servidor, no fuso da igreja: não dá para marcar dias antigos.
 */
class RecordReadingCheckin
{
    public function __construct(
        private readonly AwardBadges $awardBadges,
    ) {}

    /**
     * @return list<Badge> selos conquistados com este check-in
     */
    public function handle(User $user, Lesson $lesson, ?LessonReading $reading, bool $yesterday = false): array
    {
        if (! $user->isMemberOf($lesson->classroom_id) || Gate::forUser($user)->denies('view', $lesson)) {
            abort(403);
        }

        if ($reading !== null && $reading->lesson_id !== $lesson->id) {
            throw ValidationException::withMessages(['reading_id' => 'Esta leitura não é desta lição.']);
        }

        $date = $yesterday ? ChurchCalendar::today()->subDay() : ChurchCalendar::today();

        DB::table('reading_checkins')->insertOrIgnore([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'lesson_reading_id' => $reading?->id,
            'read_on' => $date->toDateString(),
            'created_at' => now(),
        ]);

        return $this->awardBadges->handle($user, $lesson->series);
    }
}
