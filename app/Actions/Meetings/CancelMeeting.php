<?php

namespace App\Actions\Meetings;

use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Support\ProgramNames;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Não teve EBD neste domingo". A lição atual segue para o próximo encontro.
 */
class CancelMeeting
{
    public function __construct(
        private readonly SyncUpcomingMeeting $syncMeeting,
    ) {}

    public function handle(ClassMeeting $meeting, ?string $reason): void
    {
        if ($meeting->hasAttendance()) {
            throw ValidationException::withMessages([
                'meeting' => 'Este encontro já tem chamada registrada e não pode ser marcado como "sem '.app(ProgramNames::class)->shortFor($meeting->classroom_id).'".',
            ]);
        }

        DB::transaction(function () use ($meeting, $reason) {
            // A classe antes do encontro: a mesma ordem de trava da projeção.
            Classroom::query()->lockForUpdate()->findOrFail($meeting->classroom_id);
            $meeting->update([
                'status' => MeetingStatus::Cancelled,
                'lesson_id' => null,
                'title' => filled($reason) ? $reason : $meeting->title,
            ]);

            $this->syncMeeting->handle($meeting->classroom);
        });
    }
}
