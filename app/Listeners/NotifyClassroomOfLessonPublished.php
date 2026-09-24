<?php

namespace App\Listeners;

use App\Actions\Notifications\NotifyLessonPublished;
use App\Events\LessonPublished;

/**
 * Um problema no envio das notificações nunca pode impedir a publicação.
 */
class NotifyClassroomOfLessonPublished
{
    public function __construct(private readonly NotifyLessonPublished $notify) {}

    /**
     * @return int|null aparelhos avisados; null quando o envio falhou (volta para quem disparou o evento)
     */
    public function handle(LessonPublished $event): ?int
    {
        try {
            return $this->notify->handle($event->lesson);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
