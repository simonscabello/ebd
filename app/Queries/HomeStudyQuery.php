<?php

namespace App\Queries;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Queries\Data\AttendanceBook;
use Illuminate\Support\Facades\DB;

/**
 * Estudo em casa: quantos dos alunos que contavam no domingo da lição marcaram
 * leitura, quantos dias em média e quantos dias tem o plano (dias da semana
 * com leitura). Só alunos atuais e só as lições da própria classe.
 */
class HomeStudyQuery
{
    /**
     * @param  array<int, string>  $sundays  id da lição => Y-m-d do (primeiro) domingo dela
     * @return array<int, array{lesson_id: int, lesson_title: string, readings_total: int, readers: int, expected: int, rate: int|null, avg_days: float, days: array<int, int>}>
     */
    public function forLessons(array $sundays, AttendanceBook $book): array
    {
        if ($sundays === []) {
            return [];
        }

        $ids = array_keys($sundays);

        $plan = DB::table('lesson_readings')
            ->whereIn('lesson_id', $ids)
            ->whereNotNull('weekday')
            ->groupBy('lesson_id')
            ->selectRaw('lesson_id, COUNT(DISTINCT weekday) AS days')
            ->pluck('days', 'lesson_id');

        $read = DB::table('reading_checkins')
            ->whereIn('lesson_id', $ids)
            ->whereIn('user_id', array_keys($book->since))
            ->groupBy('lesson_id', 'user_id')
            ->selectRaw('lesson_id, user_id, COUNT(DISTINCT weekday) AS days')
            ->get()
            ->groupBy('lesson_id');

        $lessons = Lesson::query()->withTrashed()->whereKey($ids)->get(['id', 'number', 'title'])->keyBy('id');

        $result = [];

        foreach ($sundays as $lessonId => $sunday) {
            $expected = array_keys(array_filter($book->since, fn (string $since) => $since <= $sunday));
            $days = [];

            foreach ($read->get($lessonId, collect()) as $row) {
                if (in_array((int) $row->user_id, $expected, true)) {
                    $days[(int) $row->user_id] = (int) $row->days;
                }
            }

            $readers = count($days);

            $result[$lessonId] = [
                'lesson_id' => $lessonId,
                'lesson_title' => $lessons->get($lessonId)?->displayTitle() ?? 'Lição removida',
                'readings_total' => max((int) ($plan[$lessonId] ?? 0), 1),
                'readers' => $readers,
                'expected' => count($expected),
                'rate' => AttendanceBook::rate($readers, count($expected)),
                'avg_days' => $readers > 0 ? round(array_sum($days) / $readers, 1) : 0.0,
                'days' => $days,
            ];
        }

        return $result;
    }

    /**
     * Última leitura marcada (Y-m-d) de cada aluno nas lições desta classe.
     *
     * @param  list<int>  $userIds
     * @return array<int, string>
     */
    public function lastReadOn(Classroom $classroom, array $userIds): array
    {
        return DB::table('reading_checkins')
            ->join('lessons', 'lessons.id', '=', 'reading_checkins.lesson_id')
            ->where('lessons.classroom_id', $classroom->id)
            ->whereIn('reading_checkins.user_id', $userIds)
            ->groupBy('reading_checkins.user_id')
            ->selectRaw('reading_checkins.user_id, MAX(reading_checkins.read_on) AS last_read')
            ->pluck('last_read', 'user_id')
            ->mapWithKeys(fn ($date, $id) => [(int) $id => substr((string) $date, 0, 10)])
            ->all();
    }
}
