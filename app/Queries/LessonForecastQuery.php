<?php

namespace App\Queries;

use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Support\ChurchCalendar;
use Carbon\CarbonImmutable;

/**
 * Previsão de quando caem as próximas lições da série: a lição atual fica no
 * próximo encontro dela e cada seguinte ocupa um encontro depois. Só conta,
 * nada é gravado: se a atual ganha mais um encontro, tudo anda uma semana.
 *
 * Semana com "sem EBD" ou evento com título fica de fora; um encontro em
 * aberto já marcado empresta a data dele (pode não ser no dia de costume).
 */
class LessonForecastQuery
{
    public function __construct(private readonly NextLessonsQuery $nextLessons) {}

    /**
     * @return array<int, CarbonImmutable> id da lição => data prevista
     */
    public function for(Classroom $classroom, ?int $limit = null): array
    {
        $current = $classroom->currentLesson;

        if ($current === null || ! $classroom->holdsMeetings()) {
            return [];
        }

        $start = ClassMeeting::query()
            ->whereBelongsTo($classroom)
            ->where('lesson_id', $current->id)
            ->where('status', MeetingStatus::Planned)
            ->whereDate('held_on', '>=', ChurchCalendar::todayString())
            // O mesmo "em aberto" de SyncUpcomingMeeting: evento ou chamada feita não é o encontro da lição.
            ->whereNull('attendance_taken_at')
            ->whereNull('finished_at')
            ->whereNull('title')
            ->min('held_on');

        if ($start === null) {
            return [];
        }

        $start = CarbonImmutable::parse($start, ChurchCalendar::timezone());
        $later = ClassMeeting::query()
            ->whereBelongsTo($classroom)
            ->whereDate('held_on', '>', $start->toDateString())
            ->get(['held_on', 'status', 'title']);

        // O dia de costume da semana do encontro atual; cada lição pula uma semana.
        $day = ChurchCalendar::onOrAfter($start, $classroom->meeting_weekday);
        $forecast = [];

        foreach ($this->nextLessons->for($classroom, $current, $limit) as $lesson) {
            // Termina: os encontros que tomam a semana são finitos.
            while (true) {
                $day = $day->addWeek();
                $week = $later->filter(fn (ClassMeeting $m) => $m->held_on->toDateString() > $day->subWeek()->toDateString()
                    && $m->held_on->toDateString() <= $day->toDateString());

                if ($week->contains(fn (ClassMeeting $m) => $m->status !== MeetingStatus::Planned || $m->title !== null)) {
                    continue;
                }

                $open = $week->first();
                $forecast[$lesson->id] = $open === null
                    ? $day
                    : CarbonImmutable::parse($open->held_on->toDateString(), ChurchCalendar::timezone());

                break;
            }
        }

        return $forecast;
    }
}
