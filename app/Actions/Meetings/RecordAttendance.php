<?php

namespace App\Actions\Meetings;

use App\Actions\Engagement\AwardBadges;
use App\Enums\ClassroomRole;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\User;
use App\Support\ChurchCalendar;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Chamada do Modo Domingo. Recebe a lista completa de presentes (sincroniza),
 * então reenviar por causa de Wi-Fi ruim não duplica nada.
 */
class RecordAttendance
{
    public function __construct(
        private readonly AwardBadges $awardBadges,
    ) {}

    /**
     * @param  list<int>  $presentIds
     */
    public function handle(ClassMeeting $meeting, array $presentIds, int $visitors, User $by): void
    {
        if ($meeting->isCancelled()) {
            throw ValidationException::withMessages(['meeting' => 'Não teve EBD neste dia.']);
        }

        if ($meeting->held_on->toDateString() > ChurchCalendar::today()->toDateString()) {
            throw ValidationException::withMessages(['meeting' => 'A chamada só pode ser feita no dia do encontro ou depois.']);
        }

        $presentIds = array_values(array_unique(array_map('intval', $presentIds)));

        $students = DB::table('classroom_user')
            ->where('classroom_id', $meeting->classroom_id)
            ->where('role', ClassroomRole::Student->value)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (array_diff($presentIds, $students) !== []) {
            throw ValidationException::withMessages(['present' => 'A lista tem pessoas que não são alunos desta classe.']);
        }

        DB::transaction(function () use ($meeting, $presentIds, $visitors, $by) {
            $meeting->attendances()->whereNotIn('user_id', $presentIds)->delete();

            $rows = array_map(fn (int $id) => [
                'class_meeting_id' => $meeting->id,
                'user_id' => $id,
                'recorded_by' => $by->id,
                'created_at' => now(),
            ], $presentIds);

            DB::table('attendances')->insertOrIgnore($rows);

            $meeting->forceFill([
                'attendance_taken_at' => $meeting->attendance_taken_at ?? now(),
                'visitors_count' => max(0, $visitors),
                'status' => MeetingStatus::Held,
            ])->save();
        });

        $series = $meeting->lesson?->series;

        if ($series !== null) {
            User::query()->whereKey($presentIds)->get()
                ->each(fn (User $student) => $this->awardBadges->handle($student, $series));
        }
    }
}
