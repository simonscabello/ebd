<?php

namespace App\Actions\Notifications;

use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Support\ChurchCalendar;
use App\Support\Push\PushMessage;
use App\Support\Push\PushSender;
use Carbon\CarbonImmutable;

/**
 * Véspera da EBD (sábado de manhã): se a classe tem encontro amanhã, lembra a
 * lição. Sem encontro (ou domingo sem EBD), silêncio.
 */
final class SendLessonReminder
{
    public function __construct(private readonly PushSender $sender) {}

    /**
     * @return int aparelhos avisados
     */
    public function handle(?CarbonImmutable $today = null): int
    {
        $tomorrow = ($today ?? ChurchCalendar::today())->addDay();
        $sent = 0;

        foreach (Classroom::query()->active()->get() as $classroom) {
            $meeting = ClassMeeting::query()
                ->whereBelongsTo($classroom)
                ->active()
                ->whereDate('held_on', $tomorrow->toDateString())
                ->with('lesson')
                ->first();

            if ($meeting === null) {
                continue;
            }

            $recipients = SendReadingReminders::subscribedMembers($classroom);

            if ($recipients->isEmpty()) {
                continue;
            }

            $lesson = $meeting->lesson;
            $visible = $lesson !== null && $lesson->status->isVisible();

            $message = new PushMessage(
                title: 'Amanhã tem EBD!',
                body: $visible
                    ? $lesson->displayTitle().($lesson->bible_reference ? " · {$lesson->bible_reference}" : '')
                    : "Nos vemos amanhã na classe {$classroom->name}.",
                url: $visible ? route('lessons.show', $lesson->slug) : route('home', ['classe' => $classroom->slug]),
                tag: "meeting:{$meeting->id}",
            );

            $sent += $this->sender->send($recipients->flatMap->pushSubscriptions->values(), $message);
        }

        return $sent;
    }
}
