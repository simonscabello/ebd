<?php

namespace App\Actions\Notifications;

use App\Models\Lesson;
use App\Support\Push\PushMessage;
use App\Support\Push\PushSender;

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

        // O título da notificação cabe numa linha só: o nome da lição vai no
        // começo do corpo, que o celular mostra inteiro ao abrir o aviso.
        $details = PushMessage::excerpt($lesson->summary)
            ?? ($lesson->bible_reference ? "Texto base: {$lesson->bible_reference}" : 'Já está disponível para estudo.');

        return $this->sender->send($recipients->flatMap->pushSubscriptions->values(), new PushMessage(
            title: $lesson->number ? "Nova lição · Lição {$lesson->number}" : 'Nova lição',
            body: PushMessage::excerpt($lesson->title, 120)."\n".$details,
            url: route('lessons.show', $lesson->slug),
            tag: "lesson:{$lesson->id}",
        ));
    }
}
