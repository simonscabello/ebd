<?php

namespace Tests\Unit;

use App\Enums\Weekday;
use PHPUnit\Framework\TestCase;

/**
 * Textos do dia do encontro: para domingo, os mesmos de sempre.
 */
class WeekdayTest extends TestCase
{
    public function test_meeting_names(): void
    {
        $this->assertSame('Domingo', Weekday::Sunday->meetingName());
        $this->assertSame('Encontro', Weekday::Wednesday->meetingName());
        $this->assertSame('Domingos', Weekday::Sunday->meetingNamePlural());
        $this->assertSame('Encontros', Weekday::Wednesday->meetingNamePlural());
    }

    public function test_lower_label_and_next_meeting_phrase(): void
    {
        $this->assertSame('quarta-feira', Weekday::Wednesday->lowerLabel());
        $this->assertSame('domingo', Weekday::Sunday->lowerLabel());
        $this->assertSame('no próximo domingo', Weekday::Sunday->nextMeetingPhrase());
        $this->assertSame('no próximo sábado', Weekday::Saturday->nextMeetingPhrase());
        $this->assertSame('na próxima quarta-feira', Weekday::Wednesday->nextMeetingPhrase());
    }
}
