<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Series;
use App\Queries\AttendanceBookQuery;
use App\Queries\CurrentLessonQuery;
use App\Queries\Data\AttendanceBook;
use App\Queries\Data\Period;
use App\Queries\HomeStudyQuery;
use App\Support\ChurchCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Relatório da classe, fechado por série (revista): a chamada aluno a aluno,
 * domingo a domingo, a frequência por gênero e o estudo em casa por lição.
 * Pensado também para imprimir.
 */
class ClassroomReportController extends Controller
{
    public function __invoke(
        Request $request,
        Classroom $classroom,
        AttendanceBookQuery $books,
        HomeStudyQuery $homeStudy,
        CurrentLessonQuery $current,
    ): Response {
        Gate::authorize('viewInsights', $classroom);

        $series = $classroom->series()->orderByDesc('starts_on')->orderByDesc('id')->get();
        $chosen = $this->chosenSeries($request, $classroom, $series, $current);
        $period = $chosen !== null ? Period::forSeries($chosen) : Period::lastMonths(3);
        $book = $books->for($classroom, $period);

        $students = $book->students
            ->map(fn (object $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'gender' => $s->gender,
                'cells' => $book->meetings->map(fn (ClassMeeting $m) => $book->cell($s->id, $m))->values(),
                ...array_intersect_key($book->student($s->id), array_flip(['present', 'expected', 'rate'])),
            ])
            // Quem entrou depois do período não tem o que mostrar aqui.
            ->filter(fn (array $row) => $row['expected'] > 0)
            ->values();

        return Inertia::render('admin/classrooms/report', [
            'classroom' => ClassroomResource::make($classroom),
            'period' => [
                'label' => $period->label,
                'from' => $period->from,
                'to' => $period->to,
                'series_id' => $period->seriesId,
            ],
            'options' => [
                ...$series->map(fn (Series $s) => ['value' => "serie:{$s->id}", 'label' => $s->title])->all(),
                ['value' => 'periodo:3m', 'label' => 'Últimos 3 meses'],
            ],
            'selected' => $chosen !== null ? "serie:{$chosen->id}" : 'periodo:3m',
            'sundays' => $book->meetings->map(fn (ClassMeeting $m) => [
                'id' => $m->id,
                'held_on' => $m->held_on->toDateString(),
                'lesson' => $m->lesson?->displayTitle(),
                'title' => $m->title,
                ...$book->sunday($m),
            ])->values(),
            'students' => $students,
            'totals' => $book->totals(),
            'byGender' => $this->byGender($students->all()),
            'homeStudy' => array_values(array_map(
                fn (array $row) => array_diff_key($row, ['days' => true]),
                $homeStudy->forLessons($this->lessonSundays($book), $book),
            )),
            'printedAt' => ChurchCalendar::todayString(),
        ]);
    }

    /**
     * ?serie=… escolhe a série; ?periodo=3m, os últimos 3 meses. Sem nada, a
     * série da lição da semana; senão a que está em andamento; senão a última.
     *
     * @param  Collection<int, Series>  $series
     */
    private function chosenSeries(Request $request, Classroom $classroom, Collection $series, CurrentLessonQuery $current): ?Series
    {
        if ($request->query('periodo') === '3m') {
            return null;
        }

        if ($request->filled('serie')) {
            return $series->firstWhere('id', (int) $request->query('serie')) ?? abort(404);
        }

        $today = ChurchCalendar::todayString();
        $weekSeriesId = $current->for($classroom, $request->user())->lesson?->series_id;

        return $series->firstWhere('id', $weekSeriesId)
            ?? $series->first(fn (Series $s) => $s->starts_on !== null && $s->starts_on->toDateString() <= $today && ($s->ends_on === null || $s->ends_on->toDateString() >= $today))
            ?? $series->first();
    }

    /**
     * Primeiro domingo de cada lição do período (o estudo em casa é da semana
     * antes dele).
     *
     * @return array<int, string>
     */
    private function lessonSundays(AttendanceBook $book): array
    {
        $sundays = [];

        foreach ($book->meetings as $meeting) {
            if ($meeting->lesson_id !== null && ! isset($sundays[$meeting->lesson_id])) {
                $sundays[$meeting->lesson_id] = $meeting->held_on->toDateString();
            }
        }

        return $sundays;
    }

    /**
     * @param  array<int, array{gender: string|null, present: int, expected: int}>  $students
     * @return list<array{gender: string|null, label: string, students: int, present: int, expected: int, rate: int|null}>
     */
    private function byGender(array $students): array
    {
        $groups = [];

        foreach ([...array_map(fn (Gender $g) => $g->value, Gender::cases()), null] as $gender) {
            $rows = array_filter($students, fn (array $row) => $row['gender'] === $gender);

            if ($rows === []) {
                continue;
            }

            $present = array_sum(array_column($rows, 'present'));
            $expected = array_sum(array_column($rows, 'expected'));

            $groups[] = [
                'gender' => $gender,
                'label' => $gender !== null ? Gender::from($gender)->label() : 'Não informado',
                'students' => count($rows),
                'present' => $present,
                'expected' => $expected,
                'rate' => AttendanceBook::rate($present, $expected),
            ];
        }

        return $groups;
    }
}
