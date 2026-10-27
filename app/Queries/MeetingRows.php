<?php

namespace App\Queries;

use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Queries\Data\AttendanceBook;
use App\Support\ProgramNames;
use Illuminate\Support\Collection;

/**
 * Linhas da agenda da classe (lista de Domingos e agenda da classe): lição,
 * chamada, quem faltou e "domingo 2 de 2" de cada encontro.
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
                'slug' => $meeting->lesson->slug,
                'display_title' => $meeting->lesson->displayTitle(),
                'status' => $meeting->lesson->status->value,
            ] : null,
            'has_attendance' => $meeting->hasAttendance(),
            'summary' => $meeting->hasAttendance() ? $book->sunday($meeting) : null,
            'position' => $positions[$meeting->id] ?? null,
            'is_today' => $date === $today,
            'awaiting_confirmation' => $meeting->status->value === 'planned' && $date < $today,
            'has_notes' => filled($meeting->notes),
            'absent' => $meeting->hasAttendance() ? $this->absent($meeting, $book) : [],
        ];
    }

    /**
     * Quem faltou no encontro, com as faltas seguidas dentro do livro.
     *
     * @return list<array{id: int, name: string, phone: string|null, missed_in_a_row: int}>
     */
    private function absent(ClassMeeting $meeting, AttendanceBook $book): array
    {
        $present = $book->presentIds($meeting);
        $date = $meeting->held_on->toDateString();

        return array_values($book->students
            ->filter(fn (object $s) => $book->isExpected($s->id, $date) && ! in_array($s->id, $present, true))
            ->map(fn (object $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'phone' => $s->phone,
                'missed_in_a_row' => $book->student($s->id)['missed_in_a_row'],
            ])
            ->all());
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
