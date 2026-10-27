<?php

namespace App\Actions\Engagement;

use App\Enums\Badge;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use App\Support\Enrollment;
use App\Support\ReadingWeeks;
use App\Support\StudyStreak;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Confere e concede selos depois de cada ação do aluno (leitura marcada,
 * chamada). Idempotente: um selo nunca é concedido duas vezes.
 *
 * @return list<Badge> selos recém-conquistados
 */
class AwardBadges
{
    public function __construct(
        private readonly StudyStreak $streak,
    ) {}

    /**
     * @return list<Badge>
     */
    public function handle(User $user, ?Series $series = null): array
    {
        $awarded = [];
        $streak = $this->streak->for($user);

        if ($streak['best'] >= 7) {
            $awarded[] = $this->award($user, Badge::Streak7);
        }

        if ($streak['best'] >= 30) {
            $awarded[] = $this->award($user, Badge::Streak30);
        }

        if ($this->hasFullWeek($user)) {
            $awarded[] = $this->award($user, Badge::FirstFullWeek);
        }

        if ($series !== null) {
            if ($this->isFaithfulReader($user, $series)) {
                $awarded[] = $this->award($user, Badge::FaithfulReader, $series);
            }

            if ($this->hasPerfectAttendance($user, $series)) {
                $awarded[] = $this->award($user, Badge::PerfectAttendance, $series);
            }
        }

        return array_values(array_filter($awarded));
    }

    /**
     * Grava o selo se ainda não existir; devolve-o só quando é novo.
     */
    private function award(User $user, Badge $badge, ?Series $series = null): ?Badge
    {
        $inserted = DB::table('user_badges')->insertOrIgnore([
            'user_id' => $user->id,
            'badge' => $badge->value,
            'series_id' => $badge->isPerSeries() ? $series?->id : null,
            'awarded_at' => now(),
        ]);

        return $inserted > 0 ? $badge : null;
    }

    /**
     * Quanto falta para cada selo ainda não conquistado: o mesmo critério do
     * handle(), em números. Os selos por série olham a série informada.
     *
     * @return array<value-of<Badge>, array{current: int, target: int}|null>
     */
    public function progress(User $user, ?Series $series): array
    {
        $best = $this->streak->for($user)['best'];

        return [
            Badge::FirstFullWeek->value => ['current' => min($this->bestWeekDays($user), 6), 'target' => 6],
            Badge::Streak7->value => ['current' => min($best, 7), 'target' => 7],
            Badge::Streak30->value => ['current' => min($best, 30), 'target' => 30],
            Badge::FaithfulReader->value => $series ? $this->faithfulReaderProgress($user, $series) : null,
            Badge::PerfectAttendance->value => $series ? $this->attendanceProgress($user, $series) : null,
        ];
    }

    /** Marcou as leituras dos seis dias antes do encontro de uma mesma lição. */
    private function hasFullWeek(User $user): bool
    {
        return $this->bestWeekDays($user) >= 6;
    }

    /**
     * Maior número de dias de leitura (fora o dia do encontro) numa mesma
     * semana de leitura de uma lição: numa lição que continua, cada semana
     * conta sozinha (ver ReadingWeeks). O dia do encontro é o da data do
     * encontro daquela semana, não o dia atual da turma: depois de uma troca
     * de dia, as lições antigas continuam contando como contavam.
     */
    private function bestWeekDays(User $user): int
    {
        $rows = DB::table('reading_checkins')
            ->join('lessons', 'lessons.id', '=', 'reading_checkins.lesson_id')
            ->join('classrooms', 'classrooms.id', '=', 'lessons.classroom_id')
            ->where('reading_checkins.user_id', $user->id)
            ->get(['reading_checkins.lesson_id', 'reading_checkins.weekday', 'reading_checkins.read_on', 'classrooms.meeting_weekday']);

        $dates = ReadingWeeks::meetingDates(array_values(array_unique($rows->map(fn (object $row) => (int) $row->lesson_id)->all())));
        $weeks = [];

        foreach ($rows as $row) {
            $lessonDates = $dates[(int) $row->lesson_id] ?? [];
            $week = ReadingWeeks::index($lessonDates, substr((string) $row->read_on, 0, 10));
            // Sem encontro (lição sem data), vale o dia da turma.
            $meetingWeekday = isset($lessonDates[$week])
                ? CarbonImmutable::parse($lessonDates[$week])->dayOfWeekIso
                : (int) $row->meeting_weekday;

            if ((int) $row->weekday === $meetingWeekday) {
                continue;
            }

            $weeks["{$row->lesson_id}:{$week}"][(int) $row->weekday] = true;
        }

        return $weeks === [] ? 0 : max(array_map('count', $weeks));
    }

    /**
     * Estudou em casa (ao menos 3 dias) em 80% das lições da série que já
     * foram dadas, com pelo menos 4 lições dadas.
     */
    private function isFaithfulReader(User $user, Series $series): bool
    {
        ['taught' => $taught, 'studied' => $studied] = $this->seriesStudy($user, $series);

        return $taught >= 4 && $studied >= (int) ceil($taught * 0.8);
    }

    /**
     * Lições estudadas sobre as necessárias. Com menos de 4 lições dadas,
     * a meta é 4 (o mínimo para o selo).
     *
     * @return array{current: int, target: int}
     */
    private function faithfulReaderProgress(User $user, Series $series): array
    {
        ['taught' => $taught, 'studied' => $studied] = $this->seriesStudy($user, $series);
        $target = max(4, (int) ceil($taught * 0.8));

        return ['current' => min($studied, $target), 'target' => $target];
    }

    /**
     * @return array{taught: int, studied: int} lições da série já dadas e,
     *                                          delas, as estudadas em ao menos 3 dias
     */
    private function seriesStudy(User $user, Series $series): array
    {
        $taught = Lesson::query()
            ->whereBelongsTo($series)
            ->whereHas('meetings', fn ($q) => $q->where('status', MeetingStatus::Held))
            ->pluck('id');

        $studied = DB::query()->fromSub(
            DB::table('reading_checkins')
                ->select('lesson_id')
                ->where('user_id', $user->id)
                ->whereIn('lesson_id', $taught)
                ->groupBy('lesson_id')
                ->havingRaw('COUNT(DISTINCT weekday) >= 3'),
            'studied',
        )->count();

        return ['taught' => $taught->count(), 'studied' => $studied];
    }

    /**
     * Presente em todos os domingos com chamada das lições da série desde que
     * entrou na classe (mínimo 4), sendo o último já no fim da série.
     */
    private function hasPerfectAttendance(User $user, Series $series): bool
    {
        $meetings = $this->seriesMeetings($user, $series);

        if ($meetings === null || $meetings->count() < 4) {
            return false;
        }

        if ($this->remainingLessons($series) > 0) {
            return false;
        }

        return $this->presences($user, $meetings) === $meetings->count();
    }

    /**
     * Presenças sobre os domingos da série: os que já tiveram chamada mais um
     * por lição que falta (o selo só sai quando a série é concluída). Meta
     * mínima de 4.
     *
     * @return array{current: int, target: int}|null
     */
    private function attendanceProgress(User $user, Series $series): ?array
    {
        $meetings = $this->seriesMeetings($user, $series);

        if ($meetings === null) {
            return null;
        }

        return [
            'current' => $this->presences($user, $meetings),
            'target' => max(4, $meetings->count() + $this->remainingLessons($series)),
        ];
    }

    /**
     * Domingos com chamada das lições da série desde que o aluno entrou na
     * classe; null se ele não conta na classe.
     *
     * @return Collection<int, int>|null
     */
    private function seriesMeetings(User $user, Series $series): ?Collection
    {
        $since = Enrollment::sinceMap($series->classroom_id, [$user->id])[$user->id] ?? null;

        if ($since === null) {
            return null;
        }

        return ClassMeeting::query()
            ->whereNotNull('attendance_taken_at')
            ->whereDate('held_on', '>=', $since)
            ->whereHas('lesson', fn ($q) => $q->whereBelongsTo($series))
            ->pluck('id');
    }

    /**
     * Lições da série que faltam: o selo de presença só sai quando o professor
     * conclui a série (antes, ao menos uma ainda falta).
     */
    private function remainingLessons(Series $series): int
    {
        if ($series->concluded_at !== null) {
            return 0;
        }

        return max(1, Lesson::query()->whereBelongsTo($series)->whereNull('studied_at')->count());
    }

    /**
     * @param  Collection<int, int>  $meetings
     */
    private function presences(User $user, Collection $meetings): int
    {
        return DB::table('attendances')
            ->where('user_id', $user->id)
            ->whereIn('class_meeting_id', $meetings)
            ->count();
    }
}
