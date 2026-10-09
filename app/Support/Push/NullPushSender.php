<?php

namespace App\Support\Push;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Sem chaves VAPID configuradas nada é enviado; fica só um aviso no log.
 */
final class NullPushSender implements PushSender
{
    public function send(Collection $subscriptions, PushMessage $message): int
    {
        if ($subscriptions->isNotEmpty()) {
            Log::info('Push ignorado: VAPID_PUBLIC_KEY/VAPID_PRIVATE_KEY não configuradas.', ['title' => $message->title, 'devices' => $subscriptions->count()]);
        }

        return 0;
    }
}
