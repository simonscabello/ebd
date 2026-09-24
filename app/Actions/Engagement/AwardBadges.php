<?php

namespace App\Actions\Engagement;

use App\Enums\Badge;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use App\Support\ChurchCalendar;
use App\Support\StudyStreak;
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

    /** Marcou as leituras de segunda a sábado de uma mesma lição. */
    private function hasFullWeek(User $user): bool
    {
        return DB::table('reading_checkins')
            ->select('lesson_id')
            ->where('user_id', $user->id)
            ->whereBetween('weekday', [1, 6])
            ->groupBy('lesson_id')
            ->havingRaw('COUNT(DISTINCT weekday) >= 6')
            ->exists();
    }

    /**
     * Estudou em casa (ao menos 3 dias) em 80% das lições da série que já
     * foram dadas, com pelo menos 4 lições dadas.
     */
    private function isFaithfulReader(User $user, Series $series): bool
    {
        $taught = Lesson::query()
            ->whereBelongsTo($series)
            ->whereHas('meetings', fn ($q) => $q->where('status', MeetingStatus::Held))
            ->pluck('id');

        if ($taught->count() < 4) {
            return false;
        }

        $studied = DB::query()->fromSub(
            DB::table('reading_checkins')
                ->select('lesson_id')
                ->where('user_id', $user->id)
                ->whereIn('lesson_id', $taught)
                ->groupBy('lesson_id')
                ->havingRaw('COUNT(DISTINCT weekday) >= 3'),
            'studied',
        )->count();

        return $studied >= (int) ceil($taught->count() * 0.8);
    }

    /**
     * Presente em todos os encontros com chamada das lições da série (mínimo 4),
     * sendo o último já no fim da série.
     */
    private function hasPerfectAttendance(User $user, Series $series): bool
    {
        $meetings = ClassMeeting::query()
            ->whereNotNull('attendance_taken_at')
            ->whereHas('lesson', fn ($q) => $q->whereBelongsTo($series))
            ->pluck('id');

        if ($meetings->count() < 4) {
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

        return DB::table('attendances')
            ->where('user_id', $user->id)
            ->whereIn('class_meeting_id', $meetings)
            ->count() === $meetings->count();
    }
}
