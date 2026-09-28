<?php

namespace App\Queries;

use App\Queries\Data\AttendanceBook;
use App\Support\ChurchCalendar;

/**
 * "Precisam de atenção": faltas seguidas nos últimos domingos com chamada ou
 * muitos dias sem marcar leitura nas lições da classe. Quem entrou há menos de
 * duas semanas fica de fora, e quem já leu o plano inteiro da lição atual não
 * conta como "sem leitura" (uma lição de dois domingos tem uma semana só).
 */
class StudentsNeedingAttention
{
    public const NEW_STUDENT_DAYS = 14;

    /**
     * @param  array<int, string>  $lastReadOn  id do aluno => Y-m-d
     * @param  array{readings_total: int, days: array<int, int>}|null  $currentStudy  estudo da lição atual
     * @return list<array{id: int, name: string, phone: string|null, reasons: list<string>, missed_in_a_row: int, last_present_on: string|null, last_read_on: string|null}>
     */
    public function for(AttendanceBook $book, array $lastReadOn, ?array $currentStudy): array
    {
        $today = ChurchCalendar::todayString();
        $missedLimit = (int) config('ebd.insights.missed_meetings');
        $inactiveLimit = (int) config('ebd.insights.inactive_days');
        $list = [];

        foreach ($book->students as $student) {
            if (ChurchCalendar::daysBetween($student->joined_on, $today) < self::NEW_STUDENT_DAYS) {
                continue;
            }

            $attendance = $book->student($student->id);
            $lastRead = $lastReadOn[$student->id] ?? null;
            $reasons = [];

            if ($attendance['missed_in_a_row'] >= $missedLimit) {
                $reasons[] = "{$attendance['missed_in_a_row']} faltas seguidas";
            }

            $finishedPlan = $currentStudy !== null
                && $currentStudy['readings_total'] > 0
                && ($currentStudy['days'][$student->id] ?? 0) >= $currentStudy['readings_total'];
            $inactiveDays = ChurchCalendar::daysBetween($lastRead ?? $student->joined_on, $today);

            if (! $finishedPlan && $inactiveDays >= $inactiveLimit) {
                $reasons[] = $lastRead !== null ? "{$inactiveDays} dias sem leitura" : 'ainda não marcou leitura';
            }

            if ($reasons !== []) {
                $list[] = [
                    'id' => $student->id,
                    'name' => $student->name,
                    'phone' => $student->phone,
                    'reasons' => $reasons,
                    'missed_in_a_row' => $attendance['missed_in_a_row'],
                    'last_present_on' => $attendance['last_present_on'],
                    'last_read_on' => $lastRead,
                ];
            }
        }

        return $list;
    }
}
