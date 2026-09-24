<?php

namespace App\Actions\Notifications;

use App\Models\Lesson;
use App\Support\Push\PushMessage;
use App\Support\Push\PushSender;
use Illuminate\Support\Str;

/**
 * Lição publicada: avisa todos os membros da classe. Quem publicou também
 * recebe: é a confirmação de que o aviso saiu como os alunos veem.
 */
final class NotifyLessonPublished
{
    public function __construct(private readonly PushSender $sender) {}

    /**
     * @return int aparelhos avisados
     */
    public function handle(Lesson $lesson): int
    {
        $lesson->loadMissing('classroom');
        $recipients = SendReadingReminders::subscribedMembers($lesson->classroom);

        if ($recipients->isEmpty()) {
            return 0;
        }

        $body = $lesson->summary
            ? Str::limit(trim($lesson->summary), 110)
            : ($lesson->bible_reference ? "Texto base: {$lesson->bible_reference}" : 'Já está disponível para estudo.');

        return $this->sender->send($recipients->flatMap->pushSubscriptions->values(), new PushMessage(
            title: 'Nova lição: '.$lesson->displayTitle(),
            body: $body,
            url: route('lessons.show', $lesson->slug),
            tag: "lesson:{$lesson->id}",
        ));
    }
}
