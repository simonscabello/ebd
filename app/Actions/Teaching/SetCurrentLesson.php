<?php

namespace App\Actions\Teaching;

use App\Actions\Meetings\SyncUpcomingMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Estudar esta agora": a lição passa a ser a atual da classe, e a série dela
 * vira a série atual (reaberta, se estava concluída). Lição avulsa não muda a
 * série atual.
 */
class SetCurrentLesson
{
    public function __construct(private readonly SyncUpcomingMeeting $syncMeeting) {}

    public function handle(Classroom $classroom, Lesson $lesson): void
    {
        if ($lesson->classroom_id !== $classroom->id || $lesson->trashed()) {
            throw ValidationException::withMessages([
                'lesson' => 'A lição escolhida não é desta classe.',
            ]);
        }

        DB::transaction(function () use ($classroom, $lesson) {
            $locked = Classroom::query()->lockForUpdate()->findOrFail($classroom->id);

            if ($lesson->series_id !== null) {
                Series::query()->whereKey($lesson->series_id)->whereNotNull('concluded_at')->update(['concluded_at' => null]);
            }

            if ($locked->current_lesson_id === $lesson->id) {
                return;
            }

            $locked->forceFill([
                'current_lesson_id' => $lesson->id,
                'current_series_id' => $lesson->series_id ?? $locked->current_series_id,
                'current_lesson_started_at' => now(),
            ])->save();

            $this->syncMeeting->handle($locked);
        });
    }
}
