<?php

namespace App\Actions\Meetings;

use App\Actions\Lessons\SyncLessonSchedule;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Não teve EBD neste domingo". Opcionalmente empurra a lição do dia (e as
 * seguintes) para os próximos encontros.
 *
 * @return int|null id da lição que ficou sem data depois do empurrão
 */
class CancelMeeting
{
    public function __construct(
        private readonly ShiftPlannedLessons $shift,
        private readonly SyncLessonSchedule $syncSchedule,
    ) {}

    public function handle(ClassMeeting $meeting, ?string $reason, bool $shiftLessons): ?int
    {
        if ($meeting->hasAttendance()) {
            throw ValidationException::withMessages([
                'meeting' => 'Este encontro já tem chamada registrada e não pode ser marcado como "sem EBD".',
            ]);
        }

        return DB::transaction(function () use ($meeting, $reason, $shiftLessons) {
            $lessonId = $meeting->lesson_id;

            $meeting->update([
                'status' => MeetingStatus::Cancelled,
                'lesson_id' => null,
                'title' => filled($reason) ? $reason : null,
            ]);

            $leftover = null;

            if ($shiftLessons && $lessonId !== null) {
                $leftover = $this->shift->handle($meeting->classroom, $meeting->held_on->addDay(), $lessonId);
            }

            $this->syncSchedule->handle($meeting->classroom_id);

            return $leftover;
        });
    }
}
