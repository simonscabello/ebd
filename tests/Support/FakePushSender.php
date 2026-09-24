<?php

namespace Tests\Support;

use App\Models\User;
use App\Support\Push\PushMessage;
use App\Support\Push\PushSender;
use Illuminate\Support\Collection;

/**
 * Registra o que seria enviado, por aparelho, sem falar com serviço de push.
 */
final class FakePushSender implements PushSender
{
    /** @var list<array{user_id: int, endpoint: string, message: PushMessage}> */
    public array $sent = [];

    public function send(Collection $subscriptions, PushMessage $message): int
    {
        foreach ($subscriptions as $subscription) {
            $this->sent[] = ['user_id' => $subscription->user_id, 'endpoint' => $subscription->endpoint, 'message' => $message];
        }

        return $subscriptions->count();
    }

    /**
     * @return Collection<int, PushMessage>
     */
    public function sentTo(User $user): Collection
    {
        return collect($this->sent)->where('user_id', $user->id)->pluck('message');
    }

    public function count(): int
    {
        return count($this->sent);
    }
}
