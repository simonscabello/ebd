<?php

namespace App\Support\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;

/**
 * Envio real via Web Push (VAPID), com as requisições em paralelo. Inscrições
 * que o serviço de push diz terem expirado (404/410) são apagadas na hora.
 */
final class WebPushSender implements PushSender
{
    public function __construct(
        private readonly string $subject,
        private readonly string $publicKey,
        private readonly string $privateKey,
        private readonly LoggerInterface $logger,
    ) {}

    public function send(Collection $subscriptions, PushMessage $message): int
    {
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        // Com um logger, a biblioteca registra os avisos de ambiente (ex.: falta
        // de GMP/BCMath) em vez de disparar um notice, que o Laravel converteria
        // em exceção e abortaria o envio inteiro.
        $webPush = new WebPush(
            ['VAPID' => ['subject' => $this->subject, 'publicKey' => $this->publicKey, 'privateKey' => $this->privateKey]],
            ['TTL' => 12 * 3600, 'urgency' => 'normal'],
            logger: $this->logger,
        );
        $webPush->setReuseVAPIDHeaders(true);

        $payload = json_encode($message->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding,
            ]), $payload);
        }

        $delivered = 0;
        $expired = [];

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $delivered++;

                continue;
            }

            if ($report->isSubscriptionExpired()) {
                $expired[] = $report->getEndpoint();

                continue;
            }

            Log::warning('Push não entregue.', ['endpoint' => $report->getEndpoint(), 'reason' => $report->getReason()]);
        }

        if ($expired !== []) {
            PushSubscription::query()->whereIn('endpoint', $expired)->delete();
        }

        return $delivered;
    }
}
