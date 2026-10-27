<?php

namespace App\Actions\Teaching;

use App\Actions\Meetings\SyncUpcomingMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Tornar série atual": a série vem primeiro nas listas e a primeira lição
 * não estudada dela vira a atual (nenhuma, se a série ainda não tem lição).
 * Série concluída é reaberta. Já atual, só troca de lição se a atual não for
 * dela (avulsa, ou nenhuma quando a série estava vazia).
 */
class SetCurrentSeries
{
    public function __construct(private readonly SyncUpcomingMeeting $syncMeeting) {}

    public function handle(Classroom $classroom, Series $series): ?Lesson
    {
        if ($series->classroom_id !== $classroom->id) {
            throw ValidationException::withMessages([
                'series' => 'A série escolhida não é desta classe.',
            ]);
        }

        return DB::transaction(function () use ($classroom, $series) {
            $locked = Classroom::query()->lockForUpdate()->findOrFail($classroom->id);

            // Relê dentro do lock: outra aba pode ter concluído a série agora.
            Series::query()->whereKey($series->id)->whereNotNull('concluded_at')->update(['concluded_at' => null]);

            if ($locked->current_series_id === $series->id && $locked->currentLesson?->series_id === $series->id) {
                return $locked->currentLesson;
            }

            $lesson = Lesson::query()
                ->where('series_id', $series->id)
                ->whereNull('studied_at')
                ->inSequence()
                ->first();

            $locked->forceFill([
                'current_series_id' => $series->id,
                'current_lesson_id' => $lesson?->id,
                'current_lesson_started_at' => $lesson !== null ? now() : null,
            ])->save();

            $this->syncMeeting->handle($locked);

            return $lesson;
        });
    }
}
