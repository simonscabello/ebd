<?php

namespace App\Listeners;

use App\Actions\Notifications\NotifyLessonPublished;
use App\Events\LessonPublished;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Um problema no envio das notificações nunca pode impedir a publicação.
 */
class NotifyClassroomOfLessonPublished
{
    public function __construct(private readonly NotifyLessonPublished $notify) {}

    public function handle(LessonPublished $event): void
    {
        $actor = Auth::user();

        try {
            $this->notify->handle($event->lesson, $actor instanceof User ? $actor->id : null);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
