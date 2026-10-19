<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Support\Push\PushMessage;
use App\Support\Push\WebPushSender;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\VAPID;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Tests\TestCase;

class WebPushSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_malformed_subscription_does_not_block_the_other_devices(): void
    {
        $good = PushSubscription::factory()->create([
            'endpoint' => 'https://push.example/good',
            ...self::browserKeys(),
        ]);
        PushSubscription::factory()->create([
            'endpoint' => 'https://push.example/broken',
            'public_key' => 'nao-e-uma-chave',
        ]);

        $client = new class implements ClientInterface
        {
            /** @var list<string> */
            public array $sentTo = [];

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->sentTo[] = (string) $request->getUri();

                return new Response(201);
            }
        };

        $vapid = VAPID::createVapidKeys();
        $sender = new WebPushSender('mailto:ebd@example.test', $vapid['publicKey'], $vapid['privateKey'], new NullLogger, $client);

        $delivered = $sender->send(PushSubscription::query()->orderBy('id')->get(), new PushMessage(title: 'Leitura', body: 'Hoje', url: '/', tag: 't'));

        $this->assertSame(1, $delivered);
        $this->assertSame([$good->endpoint], $client->sentTo);
        $this->assertDatabaseCount('push_subscriptions', 2);
    }

    public function test_a_failure_after_something_was_sent_is_not_retried(): void
    {
        PushSubscription::factory()->count(2)->sequence(
            ['endpoint' => 'https://push.example/a', ...self::browserKeys()],
            ['endpoint' => 'https://push.example/b', ...self::browserKeys()],
        )->create();

        $client = new class implements ClientInterface
        {
            /** @var list<string> */
            public array $sentTo = [];

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->sentTo[] = (string) $request->getUri();

                if (count($this->sentTo) === 2) {
                    throw new \RuntimeException('falha depois do primeiro envio');
                }

                return new Response(201);
            }
        };

        $vapid = VAPID::createVapidKeys();
        $sender = new WebPushSender('mailto:ebd@example.test', $vapid['publicKey'], $vapid['privateKey'], new NullLogger, $client);

        try {
            $sender->send(PushSubscription::query()->orderBy('id')->get(), new PushMessage(title: 'Leitura', body: 'Hoje', url: '/', tag: 't'));
            $this->fail('A falha deveria subir.');
        } catch (\RuntimeException $e) {
            $this->assertSame('falha depois do primeiro envio', $e->getMessage());
        }

        // Nenhum reenvio: o primeiro aparelho recebeu uma vez só.
        $this->assertSame(['https://push.example/a', 'https://push.example/b'], $client->sentTo);
    }

    /**
     * Chaves como as que um navegador gera: ponto P-256 não comprimido e 16 bytes de auth.
     *
     * @return array{public_key: string, auth_token: string}
     */
    private static function browserKeys(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $details = openssl_pkey_get_details($key);
        $point = "\x04".str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

        $b64 = fn (string $raw) => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        return ['public_key' => $b64($point), 'auth_token' => $b64(random_bytes(16))];
    }
}
