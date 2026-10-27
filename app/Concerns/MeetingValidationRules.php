<?php

namespace App\Concerns;

use App\Models\ClassMeeting;
use App\Models\Classroom;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/**
 * Regras da agenda (encontros, chamada, encerrar e "sem EBD"), compartilhadas
 * pelas telas de gestão e pelo servidor MCP.
 */
trait MeetingValidationRules
{
    /**
     * @return array<string, mixed>
     */
    protected function meetingRules(Classroom $classroom, ?ClassMeeting $meeting = null): array
    {
        return [
            'held_on' => [
                'required', 'date', $this->meetingDayRule($classroom, $meeting),
                Rule::unique('class_meetings', 'held_on')
                    ->where('classroom_id', $classroom->id)
                    ->ignore($meeting?->id),
            ],
            'lesson_id' => ['nullable', 'integer', Rule::exists('lessons', 'id')->where('classroom_id', $classroom->id)->whereNull('deleted_at')],
            'title' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * A data precisa cair no dia de encontro da turma. Um encontro que ficou
     * no dia antigo (a turma trocou de dia) pode ser editado sem mudar a data.
     */
    protected function meetingDayRule(Classroom $classroom, ?ClassMeeting $meeting = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($classroom, $meeting): void {
            $date = rescue(fn () => CarbonImmutable::parse((string) $value), null, false);

            if ($date !== null && $meeting !== null && $date->toDateString() === $meeting->held_on->toDateString()) {
                return;
            }

            if ($date !== null && $date->dayOfWeekIso !== $classroom->meeting_weekday->value) {
                $fail($classroom->meeting_weekday->meetingDayMessage());
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function attendanceRules(): array
    {
        return [
            'present' => ['present', 'array', 'max:500'],
            'present.*' => ['integer'],
            'visitors' => ['nullable', 'integer', 'min:0', 'max:500'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function finishMeetingRules(): array
    {
        return [
            'continues' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function cancelMeetingRules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function meetingAttributes(): array
    {
        return [
            'held_on' => 'data',
            'lesson_id' => 'lição',
            'title' => 'título',
            'notes' => 'anotações',
            'from' => 'início',
            'to' => 'fim',
            'series_id' => 'série',
            'present' => 'presentes',
            'visitors' => 'visitantes',
            'continues' => 'continua no próximo encontro',
            'reason' => 'motivo',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function meetingMessages(): array
    {
        return ['held_on.unique' => 'Já existe um encontro desta classe nesta data.'];
    }
}
