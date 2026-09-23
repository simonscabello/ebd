<?php

namespace App\Events;

use App\Models\Lesson;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando um rascunho é publicado. Ponto de extensão para
 * notificações futuras (e-mail, push do PWA, mensagem no WhatsApp).
 */
class LessonPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Lesson $lesson) {}
}
