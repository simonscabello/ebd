<?php

namespace App\Actions\Notifications;

use App\Models\Church;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Support\ChurchCalendar;
use App\Support\Push\PushMessage;
use App\Support\Push\PushSender;
use Carbon\CarbonImmutable;

/**
 * Véspera do encontro (8h do dia anterior no fuso da igreja; roda todo dia): se a turma tem
 * encontro amanhã, lembra a lição. Sem encontro (ou "sem EBD"), silêncio.
 */
final class SendLessonReminder
{
    /** Hora local do lembrete, no fuso de cada igreja. */
    public const HOUR = 8;

    public function __construct(private readonly PushSender $sender) {}

    /**
     * @param  iterable<Church>|null  $churches  só estas igrejas (padrão: todas)
     * @return int aparelhos avisados
     */
    public function handle(?CarbonImmutable $today = null, ?iterable $churches = null): int
    {
        $sent = 0;

        foreach ($churches ?? Church::query()->orderBy('id')->get() as $church) {
            $sent += ChurchCalendar::using($church, fn () => $this->remindChurch($church, ($today ?? ChurchCalendar::today())->addDay()));
        }

        return $sent;
    }

    private function remindChurch(Church $church, CarbonImmutable $tomorrow): int
    {
        $sent = 0;

        foreach (Classroom::query()->whereBelongsTo($church)->active()->get() as $classroom) {
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
                title: "Amanhã tem {$classroom->program->short_name}!",
                body: $visible
                    ? PushMessage::excerpt($lesson->displayTitle().($lesson->bible_reference ? " · {$lesson->bible_reference}" : ''))
                    : "Nos vemos amanhã na classe {$classroom->name}.",
                url: $visible ? route('lessons.show', $lesson->slug) : route('home', ['classe' => $classroom->slug]),
                tag: "meeting:{$meeting->id}",
            );

            $sent += $this->sender->send($recipients->flatMap->pushSubscriptions->values(), $message);
        }

        return $sent;
    }
}
