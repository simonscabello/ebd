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
 * Chamada de um domingo (Modo Domingo, página do domingo e MCP). Recebe a lista
 * completa de presentes da classe (sincroniza), então reenviar por causa de
 * Wi-Fi ruim não duplica nada. Só a lista dos alunos atuais é sincronizada:
 * quem saiu da classe depois daquele domingo continua na chamada dele.
 */
class RecordAttendance
{
    public function __construct(
        private readonly AwardBadges $awardBadges,
    ) {}

    /**
     * @param  list<int>  $presentIds
     * @param  int|null  $visitors  null mantém o número já registrado
     */
    public function handle(ClassMeeting $meeting, array $presentIds, ?int $visitors, User $by): void
    {
        if ($meeting->isCancelled()) {
            throw ValidationException::withMessages(['meeting' => 'Não teve EBD neste dia.']);
        }

        if ($meeting->held_on->toDateString() > ChurchCalendar::todayString()) {
            throw ValidationException::withMessages(['meeting' => 'A chamada só pode ser feita no dia do domingo ou depois.']);
        }

        $presentIds = array_values(array_unique(array_map('intval', $presentIds)));

        $roster = DB::table('classroom_user')
            ->where('classroom_id', $meeting->classroom_id)
            ->where('role', ClassroomRole::Student->value)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $before = $meeting->attendances()->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        if (array_diff($presentIds, $roster, $before) !== []) {
            throw ValidationException::withMessages(['present' => 'A lista tem pessoas que não são alunos desta classe.']);
        }

        DB::transaction(function () use ($meeting, $presentIds, $roster, $visitors, $by) {
            $meeting->attendances()
                ->whereIn('user_id', $roster)
                ->whereNotIn('user_id', $presentIds)
                ->delete();

            $rows = array_map(fn (int $id) => [
                'class_meeting_id' => $meeting->id,
                'user_id' => $id,
                'recorded_by' => $by->id,
                'created_at' => now(),
            ], array_values(array_intersect($presentIds, $roster)));

            DB::table('attendances')->insertOrIgnore($rows);

            $meeting->forceFill([
                'attendance_taken_at' => $meeting->attendance_taken_at ?? now(),
                'visitors_count' => $visitors !== null ? max(0, $visitors) : $meeting->visitors_count,
                'status' => MeetingStatus::Held,
            ])->save();
        });

        // A chamada salva a cada toque: os selos só são conferidos para quem
        // acabou de ser marcado, não para a classe inteira toda vez.
        $newlyPresent = array_values(array_diff($presentIds, $before));
        $series = $meeting->lesson?->series;

        if ($series !== null && $newlyPresent !== []) {
            User::query()->whereKey($newlyPresent)->get()
                ->each(fn (User $student) => $this->awardBadges->handle($student, $series));
        }
    }
}
