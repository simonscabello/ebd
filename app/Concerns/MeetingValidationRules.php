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
                'required', 'date',
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
     * "Planejar trimestre": no máximo um ano de domingos por vez.
     *
     * @return array<string, mixed>
     */
    protected function meetingPlanRules(Classroom $classroom, mixed $from): array
    {
        $limit = rescue(fn () => CarbonImmutable::parse((string) $from)->addYear()->toDateString(), null, false);

        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from', ...($limit ? ["before_or_equal:{$limit}"] : [])],
            'series_id' => ['nullable', 'integer', Rule::exists('series', 'id')->where('classroom_id', $classroom->id)],
        ];
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
            'shift' => ['boolean'],
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
