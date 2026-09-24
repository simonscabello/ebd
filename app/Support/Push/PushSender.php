<?php

namespace App\Support\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Collection;

interface PushSender
{
    /**
     * Envia a mensagem para cada aparelho. Devolve quantos aceitaram.
     *
     * @param  Collection<int, PushSubscription>  $subscriptions
     */
    public function send(Collection $subscriptions, PushMessage $message): int;
}
