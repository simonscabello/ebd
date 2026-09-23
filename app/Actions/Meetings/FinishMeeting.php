<?php

namespace App\Actions\Meetings;

use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Support\ChurchCalendar;
use Illuminate\Validation\ValidationException;

/**
 * "Encerrar aula" no Modo Domingo: registra onde a turma parou e se a lição
 * terminou ou continua no próximo encontro.
 *
 * @return int|null id da lição que ficou sem data (quando continua)
 */
class FinishMeeting
{
    public function __construct(
        private readonly ContinueLessonNextMeeting $continue,
    ) {}

    public function handle(ClassMeeting $meeting, bool $continues, ?string $notes): ?int
    {
        if ($meeting->isCancelled() || $meeting->held_on->toDateString() > ChurchCalendar::today()->toDateString()) {
            throw ValidationException::withMessages(['meeting' => 'Este encontro não pode ser encerrado.']);
        }

        $meeting->update([
            'status' => MeetingStatus::Held,
            'notes' => filled($notes) ? $notes : $meeting->notes,
        ]);

        return $continues ? $this->continue->handle($meeting) : null;
    }
}
