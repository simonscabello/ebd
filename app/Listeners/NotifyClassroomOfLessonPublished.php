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

    public function handle(LessonPublished $event): void
    {
        try {
            $this->notify->handle($event->lesson);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
