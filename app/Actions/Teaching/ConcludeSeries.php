<?php

namespace App\Actions\Teaching;

use App\Actions\Meetings\SyncUpcomingMeeting;
use App\Models\Classroom;
use App\Models\Series;
use Illuminate\Support\Facades\DB;

/**
 * "Marcar como concluída": a série vai para o fim das listas. Se era a série
 * atual, a classe fica sem série atual; a lição atual dela conta como
 * estudada e deixa de ser a atual (lição avulsa atual continua).
 */
class ConcludeSeries
{
    public function __construct(private readonly SyncUpcomingMeeting $syncMeeting) {}

    public function handle(Series $series): void
    {
        DB::transaction(function () use ($series) {
            $classroom = Classroom::query()->lockForUpdate()->findOrFail($series->classroom_id);

            $series->forceFill(['concluded_at' => now()])->save();

            if ($classroom->current_series_id !== $series->id) {
                return;
            }

            $lesson = $classroom->currentLesson;
            $inSeries = $lesson !== null && $lesson->series_id === $series->id;

            if ($inSeries && $lesson->studied_at === null) {
                $lesson->forceFill(['studied_at' => now()])->save();
            }

            $classroom->forceFill([
                'current_series_id' => null,
                'current_lesson_id' => $inSeries ? null : $classroom->current_lesson_id,
                'current_lesson_started_at' => $inSeries ? null : $classroom->current_lesson_started_at,
            ])->save();

            $this->syncMeeting->handle($classroom);
        });
    }

    /**
     * Desfaz: a série volta para a lista das em andamento (não vira a atual).
     */
    public function reopen(Series $series): void
    {
        $series->forceFill(['concluded_at' => null])->save();
    }
}
