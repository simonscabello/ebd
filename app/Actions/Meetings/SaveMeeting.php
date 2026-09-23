<?php

namespace App\Actions\Meetings;

use App\Actions\Lessons\SyncLessonSchedule;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria ou edita um encontro. A lição precisa ser da mesma classe.
 */
class SaveMeeting
{
    public function __construct(
        private readonly SyncLessonSchedule $syncSchedule,
    ) {}

    /**
     * @param  array{held_on?: string, lesson_id?: int|string|null, title?: string|null, notes?: string|null}  $data
     */
    public function handle(Classroom $classroom, array $data, ?ClassMeeting $meeting = null): ClassMeeting
    {
        if (array_key_exists('lesson_id', $data) && $data['lesson_id'] !== null) {
            $belongs = Lesson::query()->whereKey($data['lesson_id'])->where('classroom_id', $classroom->id)->exists();

            if (! $belongs) {
                throw ValidationException::withMessages(['lesson_id' => 'A lição escolhida não pertence a esta classe.']);
            }
        }

        return DB::transaction(function () use ($classroom, $data, $meeting) {
            $meeting ??= new ClassMeeting(['status' => MeetingStatus::Planned]);
            $meeting->classroom_id = $classroom->id;
            $meeting->fill(Arr::only($data, ['held_on', 'lesson_id', 'title', 'notes']));

            // Encontro cancelado que recebe lição volta a ser planejado.
            if ($meeting->isCancelled() && $meeting->lesson_id !== null) {
                $meeting->status = MeetingStatus::Planned;
            }

            $meeting->save();

            $this->syncSchedule->handle($classroom);

            return $meeting;
        });
    }
}
