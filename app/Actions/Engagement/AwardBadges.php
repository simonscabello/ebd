<?php

namespace App\Actions\Engagement;

use App\Enums\Badge;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use App\Support\ChurchCalendar;
use App\Support\Enrollment;
use App\Support\StudyStreak;
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

    /** Marcou as leituras de segunda a sábado de uma mesma lição. */
    private function hasFullWeek(User $user): bool
    {
        return $this->bestWeekDays($user) >= 6;
    }

    /** Maior número de dias (segunda a sábado) marcados numa mesma lição. */
    private function bestWeekDays(User $user): int
    {
        return (int) DB::query()->fromSub(
            DB::table('reading_checkins')
                ->where('user_id', $user->id)
                ->whereBetween('weekday', [1, 6])
                ->groupBy('lesson_id')
                ->selectRaw('COUNT(DISTINCT weekday) AS days'),
            'weeks',
        )->max('days');
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

        $pending = ClassMeeting::query()
            ->where('status', MeetingStatus::Planned)
            ->whereDate('held_on', '>=', ChurchCalendar::today()->toDateString())
            ->whereHas('lesson', fn ($q) => $q->whereBelongsTo($series))
            ->exists();

        if ($pending) {
            return false;
        }

        return $this->presences($user, $meetings) === $meetings->count();
    }

    /**
     * Presenças sobre os domingos com chamada da série (meta mínima de 4).
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
            'target' => max(4, $meetings->count()),
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
