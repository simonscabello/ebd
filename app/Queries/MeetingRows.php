<?php

namespace App\Queries;

use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Queries\Data\AttendanceBook;
use App\Support\ProgramNames;
use Illuminate\Support\Collection;

/**
 * Linhas da agenda da classe (lista de Domingos e linha do tempo do Resumo):
 * lição, chamada e "domingo 2 de 2" de cada encontro.
 */
class MeetingRows
{
    /**
     * @param  Collection<int, ClassMeeting>  $meetings  com a lição carregada
     * @return Collection<int, array<string, mixed>>
     */
    public function for(Classroom $classroom, Collection $meetings, AttendanceBook $book, string $today): Collection
    {
        $positions = $this->positions($classroom, $meetings);

        return $meetings->map(fn (ClassMeeting $meeting) => $this->row($meeting, $book, $positions, $today))->values();
    }

    /**
     * @param  array<int, array{index: int, total: int}>  $positions
     * @return array<string, mixed>
     */
    private function row(ClassMeeting $meeting, AttendanceBook $book, array $positions, string $today): array
    {
        $date = $meeting->held_on->toDateString();

        return [
            'id' => $meeting->id,
            'held_on' => $date,
            'status' => $meeting->status->value,
            'status_label' => app(ProgramNames::class)->statusLabel($meeting->status, $meeting->classroom_id),
            'title' => $meeting->title,
            'lesson' => $meeting->lesson ? [
                'id' => $meeting->lesson->id,
                'display_title' => $meeting->lesson->displayTitle(),
                'status' => $meeting->lesson->status->value,
            ] : null,
            'has_attendance' => $meeting->hasAttendance(),
            'summary' => $meeting->hasAttendance() ? $book->sunday($meeting) : null,
            'position' => $positions[$meeting->id] ?? null,
            'is_today' => $date === $today,
            'awaiting_confirmation' => $meeting->status->value === 'planned' && $date < $today,
            'has_notes' => filled($meeting->notes),
        ];
    }

    /**
     * "Domingo 2 de 2": posição de cada encontro entre os da mesma lição.
     *
     * @param  Collection<int, ClassMeeting>  $meetings
     * @return array<int, array{index: int, total: int}>
     */
    public function positions(Classroom $classroom, Collection $meetings): array
    {
        $lessonIds = $meetings->pluck('lesson_id')->filter()->unique()->values();

        if ($lessonIds->isEmpty()) {
            return [];
        }

        $positions = [];

        ClassMeeting::query()
            ->whereBelongsTo($classroom)
            ->whereIn('lesson_id', $lessonIds)
            ->active()
            ->chronological()
            ->get(['id', 'lesson_id'])
            ->groupBy('lesson_id')
            ->each(function (Collection $sameLesson) use (&$positions) {
                foreach ($sameLesson->values() as $index => $meeting) {
                    $positions[$meeting->id] = ['index' => $index + 1, 'total' => $sameLesson->count()];
                }
            });

        return $positions;
    }
}
