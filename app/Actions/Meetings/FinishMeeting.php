<?php

namespace App\Actions\Meetings;

use App\Actions\Teaching\AdvanceLesson;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Lesson;
use App\Support\ChurchCalendar;
use Illuminate\Validation\ValidationException;

/**
 * "Encerrar aula" na página do encontro: registra onde a turma parou e se a lição
 * terminou (a classe passa para a próxima) ou continua no próximo encontro.
 *
 * @return Lesson|null a nova lição atual, quando a lição terminou
 */
class FinishMeeting
{
    public function __construct(
        private readonly AdvanceLesson $advance,
        private readonly SyncUpcomingMeeting $syncMeeting,
    ) {}

    public function handle(ClassMeeting $meeting, bool $continues, ?string $notes): ?Lesson
    {
        if ($meeting->isCancelled() || $meeting->held_on->toDateString() > ChurchCalendar::today()->toDateString()) {
            throw ValidationException::withMessages(['meeting' => 'Este encontro não pode ser encerrado.']);
        }

        $meeting->forceFill([
            'status' => MeetingStatus::Held,
            'notes' => filled($notes) ? $notes : $meeting->notes,
            'finished_at' => now(),
        ])->save();

        // Só a lição atual avança: encerrar um encontro antigo não mexe na classe.
        if (! $continues && $meeting->lesson_id !== null) {
            return $this->advance->handle($meeting->classroom, $meeting->lesson_id);
        }

        $this->syncMeeting->handle($meeting->classroom);

        return null;
    }
}
