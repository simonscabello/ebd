<?php

namespace App\Actions\Meetings;

use App\Actions\Teaching\SetCurrentLesson;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Support\ChurchCalendar;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria ou edita um encontro. A lição precisa ser da mesma classe, e a classe
 * precisa se reunir (a biblioteca oficial não tem agenda).
 *
 * Num encontro que ainda vai acontecer, escolher a lição é escolher a lição
 * atual da classe ("Estudar esta agora"); num que já passou, corrige o
 * histórico.
 */
class SaveMeeting
{
    public function __construct(
        private readonly SetCurrentLesson $setCurrent,
        private readonly SyncUpcomingMeeting $syncMeeting,
    ) {}

    /**
     * @param  array{held_on?: string, lesson_id?: int|string|null, title?: string|null, notes?: string|null}  $data
     */
    public function handle(Classroom $classroom, array $data, ?ClassMeeting $meeting = null): ClassMeeting
    {
        $classroom->ensureHoldsMeetings();

        if (array_key_exists('lesson_id', $data) && $data['lesson_id'] !== null) {
            $belongs = Lesson::query()->whereKey($data['lesson_id'])->where('classroom_id', $classroom->id)->exists();

            if (! $belongs) {
                throw ValidationException::withMessages(['lesson_id' => 'A lição escolhida não pertence a esta classe.']);
            }
        }

        return DB::transaction(function () use ($classroom, $data, $meeting) {
            // A classe antes do encontro: a mesma ordem de trava da projeção.
            $classroom = Classroom::query()->lockForUpdate()->findOrFail($classroom->id);
            $meeting ??= new ClassMeeting(['status' => MeetingStatus::Planned]);
            $meeting->classroom_id = $classroom->id;
            $meeting->fill(Arr::only($data, ['held_on', 'lesson_id', 'title', 'notes']));

            // Encontro cancelado que recebe lição volta a ser planejado.
            if ($meeting->isCancelled() && $meeting->lesson_id !== null) {
                $meeting->status = MeetingStatus::Planned;
            }

            $meeting->save();

            $upcoming = $meeting->status === MeetingStatus::Planned
                && ! $meeting->hasAttendance()
                && $meeting->held_on->toDateString() >= ChurchCalendar::todayString();

            if ($upcoming && $meeting->lesson_id !== null && $meeting->lesson_id !== $classroom->current_lesson_id) {
                $this->setCurrent->handle($classroom, Lesson::query()->findOrFail($meeting->lesson_id));
            } else {
                $this->syncMeeting->handle($classroom);
            }

            return $meeting->refresh();
        });
    }
}
