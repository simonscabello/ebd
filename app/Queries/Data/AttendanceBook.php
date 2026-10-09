<?php

namespace App\Queries\Data;

use App\Models\ClassMeeting;
use Illuminate\Support\Collection;

/**
 * Livro de chamada de uma classe: os domingos com chamada de um período e quem
 * esteve em cada um. Toda conta de presença da gestão sai daqui, com as mesmas
 * regras: só alunos atuais, contados a partir do início de cada um (Enrollment),
 * e visitantes fora da taxa.
 */
final class AttendanceBook
{
    /**
     * @param  Collection<int, object{id: int, name: string, phone: string|null, gender: string|null, birth_date: string|null, joined_on: string}&\stdClass>  $students  alunos atuais, por nome
     * @param  array<int, string>  $since  id do aluno => primeiro dia em que ele conta
     * @param  Collection<int, ClassMeeting>  $meetings  domingos com chamada, do mais antigo ao mais recente
     * @param  array<int, list<int>>  $presence  id do domingo => ids de quem esteve
     */
    public function __construct(
        public readonly Collection $students,
        public readonly array $since,
        public readonly Collection $meetings,
        private readonly array $presence,
        public readonly Period $period,
    ) {}

    public static function rate(int $part, int $whole): ?int
    {
        return $whole > 0 ? (int) round($part / $whole * 100) : null;
    }

    public function isExpected(int $userId, string $date): bool
    {
        return isset($this->since[$userId]) && $this->since[$userId] <= $date;
    }

    /**
     * true = presente, false = faltou, null = ainda não fazia parte da classe.
     */
    public function cell(int $userId, ClassMeeting $meeting): ?bool
    {
        if (! $this->isExpected($userId, $meeting->held_on->toDateString())) {
            return null;
        }

        return in_array($userId, $this->presence[$meeting->id] ?? [], true);
    }

    /**
     * Presentes (alunos atuais) de um domingo; inclui quem foi marcado mesmo
     * antes de "contar", porque a presença antecipa o início.
     *
     * @return list<int>
     */
    public function presentIds(ClassMeeting $meeting): array
    {
        return array_values(array_filter(
            $this->presence[$meeting->id] ?? [],
            fn (int $id) => isset($this->since[$id]),
        ));
    }

    /**
     * @return array{present: int, expected: int, visitors: int, rate: int|null}
     */
    public function sunday(ClassMeeting $meeting): array
    {
        $date = $meeting->held_on->toDateString();
        $expected = count(array_filter($this->since, fn (string $since) => $since <= $date));
        $present = count($this->presentIds($meeting));

        return [
            'present' => $present,
            'expected' => $expected,
            'visitors' => $meeting->visitors_count,
            'rate' => self::rate($present, $expected),
        ];
    }

    /**
     * Totais da classe no período do livro (ou num recorte dele).
     *
     * @return array{sundays: int, present: int, expected: int, visitors: int, rate: int|null}
     */
    public function totals(?Period $within = null): array
    {
        $present = $expected = $visitors = $sundays = 0;

        foreach ($this->meetings as $meeting) {
            if ($within !== null && ! $within->contains($meeting->held_on->toDateString())) {
                continue;
            }

            $day = $this->sunday($meeting);
            $present += $day['present'];
            $expected += $day['expected'];
            $visitors += $day['visitors'];
            $sundays++;
        }

        return [
            'sundays' => $sundays,
            'present' => $present,
            'expected' => $expected,
            'visitors' => $visitors,
            'rate' => self::rate($present, $expected),
        ];
    }

    /**
     * Frequência de um aluno, faltas seguidas (do domingo mais recente para
     * trás) e a última presença, tudo só nos domingos em que ele contava.
     *
     * @return array{present: int, expected: int, rate: int|null, missed_in_a_row: int, last_present_on: string|null}
     */
    public function student(int $userId, ?Period $within = null): array
    {
        $present = $expected = $missed = 0;
        $lastPresent = null;
        $streakOpen = true;

        foreach ($this->meetings->reverse() as $meeting) {
            $cell = $this->cell($userId, $meeting);

            if ($cell === null) {
                continue;
            }

            if ($streakOpen && $cell) {
                $streakOpen = false;
            } elseif ($streakOpen) {
                $missed++;
            }

            $date = $meeting->held_on->toDateString();

            if ($cell && $lastPresent === null) {
                $lastPresent = $date;
            }

            if ($within === null || $within->contains($date)) {
                $expected++;
                $present += $cell ? 1 : 0;
            }
        }

        return [
            'present' => $present,
            'expected' => $expected,
            'rate' => self::rate($present, $expected),
            'missed_in_a_row' => $missed,
            'last_present_on' => $lastPresent,
        ];
    }

    /**
     * Domingo a domingo, do mais recente ao mais antigo, desde que o aluno conta.
     *
     * @return list<array{meeting_id: int, held_on: string, lesson: string|null, title: string|null, present: bool}>
     */
    public function history(int $userId): array
    {
        $rows = [];

        foreach ($this->meetings->reverse() as $meeting) {
            $cell = $this->cell($userId, $meeting);

            if ($cell !== null) {
                $rows[] = [
                    'meeting_id' => $meeting->id,
                    'held_on' => $meeting->held_on->toDateString(),
                    'lesson' => $meeting->lesson?->displayTitle(),
                    'title' => $meeting->title,
                    'present' => $cell,
                ];
            }
        }

        return $rows;
    }
}
