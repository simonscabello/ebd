<?php

namespace App\Support\Push;

use App\Models\PushSubscription;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;
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
        private readonly ?ClientInterface $client = null,
    ) {}

    public function send(Collection $subscriptions, PushMessage $message): int
    {
        // Inscrições antigas com endpoint fora dos serviços de push conhecidos
        // (hoje recusadas na inscrição) não recebem nada, mas não são apagadas.
        [$subscriptions, $unknown] = $subscriptions->partition(fn (PushSubscription $s) => PushEndpoint::isAllowed($s->endpoint));

        foreach ($unknown as $subscription) {
            Log::warning('Push: endpoint fora dos serviços conhecidos, pulado.', [
                'subscription_id' => $subscription->id,
                'host' => parse_url($subscription->endpoint, PHP_URL_HOST),
            ]);
        }

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $payload = json_encode($message->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $result = $this->flush($subscriptions, $payload);

        if ($result === null) {
            // A biblioteca cifra o lote inteiro antes do primeiro envio: uma
            // inscrição com chave mal formada derruba todas, e nada saiu ainda.
            // Reenvia uma por uma e pula só a que falhar.
            $result = [0, []];

            foreach ($subscriptions as $subscription) {
                $single = $this->flush(collect([$subscription]), $payload);

                if ($single === null) {
                    // O motivo já foi registrado em flush().
                    Log::warning('Push: aparelho pulado.', ['subscription_id' => $subscription->id]);

                    continue;
                }

                $result = [$result[0] + $single[0], [...$result[1], ...$single[1]]];
            }
        }

        [$delivered, $expired] = $result;

        if ($expired !== []) {
            PushSubscription::query()->whereIn('endpoint', $expired)->delete();
        }

        Log::info('Push enviado.', [
            'title' => $message->title,
            'devices' => $subscriptions->count(),
            'delivered' => $delivered,
            'expired_removed' => count($expired),
        ]);

        return $delivered;
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @return array{0: int, 1: list<string>}|null entregues e endpoints expirados;
     *                                             null quando a cifragem falhou antes de qualquer envio
     */
    private function flush(Collection $subscriptions, string $payload): ?array
    {
        // Com um logger, a biblioteca registra os avisos de ambiente (ex.: falta
        // de GMP/BCMath) em vez de disparar um notice, que o Laravel converteria
        // em exceção e abortaria o envio inteiro.
        $webPush = new WebPush(
            ['VAPID' => ['subject' => $this->subject, 'publicKey' => $this->publicKey, 'privateKey' => $this->privateKey]],
            ['TTL' => 12 * 3600, 'urgency' => 'normal'],
            // Envio em série: sem prazo, um serviço de push que não responde
            // seguraria o pedido web ("Testar notificação") e os lembretes do
            // agendador.
            client: $this->client ?? new Client(['timeout' => 10, 'connect_timeout' => 5]),
            logger: $this->logger,
        );
        $webPush->setReuseVAPIDHeaders(true);

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

        // Um lote só, para a cifragem de todos acontecer antes do primeiro
        // envio. Só uma falha nesse primeiro passo vira null; depois que algo
        // saiu, qualquer erro sobe normalmente (reenviar duplicaria avisos).
        $reports = $webPush->flush(max(1, $subscriptions->count()));

        try {
            $reports->current();
        } catch (\Throwable $e) {
            Log::warning('Push: falha ao preparar o envio.', ['devices' => $subscriptions->count(), 'error' => $e->getMessage()]);

            return null;
        }

        for (; $reports->valid(); $reports->next()) {
            $report = $reports->current();

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

        return [$delivered, $expired];
    }
}
