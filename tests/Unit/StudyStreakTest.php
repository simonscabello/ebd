<?php

namespace Tests\Unit;

use App\Support\StudyStreak;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class StudyStreakTest extends TestCase
{
    private function streak(array $dates, string $today = '2026-09-23'): array
    {
        return StudyStreak::compute($dates, CarbonImmutable::parse($today));
    }

    public function test_consecutive_days_including_today(): void
    {
        $this->assertSame(['current' => 3, 'best' => 3, 'today_done' => true], $this->streak(['2026-09-21', '2026-09-22', '2026-09-23']));
    }

    public function test_yesterday_keeps_the_streak_alive(): void
    {
        $this->assertSame(['current' => 2, 'best' => 2, 'today_done' => false], $this->streak(['2026-09-21', '2026-09-22']));
    }

    public function test_a_gap_breaks_the_current_streak_but_keeps_the_best(): void
    {
        $result = $this->streak(['2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13', '2026-09-20', '2026-09-21']);

        $this->assertSame(0, $result['current']);
        $this->assertSame(4, $result['best']);
    }

    public function test_duplicates_and_order_do_not_matter(): void
    {
        $this->assertSame(2, $this->streak(['2026-09-23', '2026-09-22', '2026-09-23'])['current']);
    }
}
