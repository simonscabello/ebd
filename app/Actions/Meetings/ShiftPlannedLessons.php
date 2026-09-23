<?php

namespace App\Actions\Meetings;

use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use Carbon\CarbonInterface;

/**
 * Empurra as lições planejadas uma casa para frente a partir de uma data,
 * inserindo $lessonId no primeiro encontro. Um encontro planejado sem lição
 * absorve o empurrão; eventos com título (sem lição) ficam onde estão.
 *
 * Devolve o id da lição que ficou sem data (sobrou no fim), se houver.
 */
class ShiftPlannedLessons
{
    public function handle(Classroom $classroom, CarbonInterface $from, int $lessonId): ?int
    {
        $slots = ClassMeeting::query()
            ->whereBelongsTo($classroom)
            ->where('status', MeetingStatus::Planned)
            ->fromDate($from)
            ->where(fn ($q) => $q->whereNotNull('lesson_id')->orWhereNull('title'))
            ->chronological()
            ->lockForUpdate()
            ->get();

        $carry = $lessonId;

        foreach ($slots as $slot) {
            $displaced = $slot->lesson_id;
            $slot->update(['lesson_id' => $carry]);

            if ($displaced === null) {
                return null;
            }

            $carry = $displaced;
        }

        return $carry;
    }
}
