<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Push\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePushSender;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc'): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-key'], 'content_encoding' => 'aes128gcm'];
    }

    public function test_a_logged_in_person_subscribes_and_unsubscribes_their_device(): void
    {
        $user = User::factory()->studentOf(Classroom::factory()->create())->create();

        $this->actingAs($user)->post('/notificacoes/inscricao', $this->payload())->assertRedirect();

        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $user->id, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'public_key' => 'p256dh-key']);

        // Repetir só atualiza.
        $this->actingAs($user)->post('/notificacoes/inscricao', [...$this->payload(), 'keys' => ['p256dh' => 'nova', 'auth' => 'auth-key']]);
        $this->assertSame(1, PushSubscription::query()->count());
        $this->assertSame('nova', PushSubscription::query()->sole()->public_key);

        $this->actingAs($user)->delete('/notificacoes/inscricao', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'])->assertRedirect();
        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_the_same_device_moves_to_whoever_logs_in(): void
    {
        $classroom = Classroom::factory()->create();
        $first = User::factory()->studentOf($classroom)->create();
        $second = User::factory()->studentOf($classroom)->create();

        $this->actingAs($first)->post('/notificacoes/inscricao', $this->payload());
        $this->actingAs($second)->post('/notificacoes/inscricao', $this->payload());

        $this->assertSame(1, PushSubscription::query()->count());
        $this->assertSame($second->id, PushSubscription::query()->sole()->user_id);

        // Ninguém apaga a inscrição de outra pessoa.
        $this->actingAs($first)->delete('/notificacoes/inscricao', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc']);
        $this->assertSame(1, PushSubscription::query()->count());
    }

    public function test_validation_and_authentication(): void
    {
        $this->post('/notificacoes/inscricao', $this->payload())->assertRedirect(route('login'));

        $user = User::factory()->create();

        $this->actingAs($user)->post('/notificacoes/inscricao', ['endpoint' => 'não é url'])
            ->assertSessionHasErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    }

    public function test_only_https_endpoints_of_known_push_services_are_accepted(): void
    {
        $user = User::factory()->create();

        foreach ([
            'https://fcm.googleapis.com/fcm/send/abc',
            'https://updates.push.services.mozilla.com/wpush/v2/abc',
            'https://web.push.apple.com/QabC',
            'https://wns2-by3p.notify.windows.com/w/?token=abc',
        ] as $endpoint) {
            $this->actingAs($user)->post('/notificacoes/inscricao', $this->payload($endpoint))->assertSessionHasNoErrors();
        }

        foreach ([
            'http://fcm.googleapis.com/fcm/send/abc',
            'https://fcm.googleapis.com.evil.example/abc',
            'https://169.254.169.254/latest/meta-data',
            'https://push.apple.com.example/abc',
            'https://fcm.googleapis.com:8443/fcm/send/abc',
            'https://user@fcm.googleapis.com/fcm/send/abc',
        ] as $endpoint) {
            $this->actingAs($user)->post('/notificacoes/inscricao', $this->payload($endpoint))->assertSessionHasErrors('endpoint');
        }

        $this->postJson('/notificacoes/renovacao', $this->payload('https://intranet.example/hook'))->assertJsonValidationErrors('endpoint');
        $this->assertSame(4, PushSubscription::query()->count());
    }

    public function test_a_person_can_send_a_test_notification_to_their_own_devices(): void
    {
        $push = new FakePushSender;
        $this->app->instance(PushSender::class, $push);
        $classroom = Classroom::factory()->create();
        $me = User::factory()->studentOf($classroom)->create(['name' => 'Ana']);
        $other = User::factory()->studentOf($classroom)->create();
        PushSubscription::factory()->for($me)->count(2)->create();
        PushSubscription::factory()->for($other)->create();

        $this->actingAs($me)->post('/notificacoes/teste')->assertRedirect();

        $this->assertSame(2, $push->count());
        $this->assertCount(0, $push->sentTo($other));
        $this->assertSame('Os lembretes estão funcionando!', $push->sentTo($me)->first()->title);

        $nobody = User::factory()->create();
        $this->actingAs($nobody)->post('/notificacoes/teste')->assertRedirect();
        $this->assertSame(2, $push->count());
    }

    public function test_a_renewed_subscription_stays_with_the_owner_of_the_old_one(): void
    {
        $user = User::factory()->studentOf(Classroom::factory()->create())->create();
        $this->actingAs($user)->post('/notificacoes/inscricao', $this->payload('https://fcm.googleapis.com/fcm/send/antiga'));
        auth()->logout();

        // O service worker chama sem sessão (e sem CSRF) quando o navegador troca a inscrição.
        $this->post('/notificacoes/renovacao', [...$this->payload('https://fcm.googleapis.com/fcm/send/nova'), 'old_endpoint' => 'https://fcm.googleapis.com/fcm/send/antiga'])
            ->assertNoContent();

        $this->assertSame(1, PushSubscription::query()->count());
        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $user->id, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/nova']);
    }

    public function test_without_the_old_subscription_the_renewal_uses_who_is_logged_in(): void
    {
        $user = User::factory()->studentOf(Classroom::factory()->create())->create();

        $this->actingAs($user)->post('/notificacoes/renovacao', $this->payload('https://fcm.googleapis.com/fcm/send/nova'))->assertNoContent();
        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $user->id, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/nova']);
    }

    public function test_a_renewal_without_a_known_owner_is_ignored(): void
    {
        $this->post('/notificacoes/renovacao', [...$this->payload(), 'old_endpoint' => 'https://fcm.googleapis.com/fcm/send/desconhecida'])
            ->assertNoContent();

        $this->assertSame(0, PushSubscription::query()->count());

        $this->postJson('/notificacoes/renovacao', ['endpoint' => 'nao-e-url'])->assertUnprocessable();
    }

    public function test_the_service_worker_can_fetch_the_public_key(): void
    {
        config(['ebd.push.public_key' => 'chave-publica']);

        $this->getJson('/notificacoes/chave')->assertOk()->assertExactJson(['public_key' => 'chave-publica']);
    }

    public function test_the_public_key_is_shared_with_the_pages(): void
    {
        config(['ebd.push.public_key' => 'chave-publica']);

        $this->get('/')->assertInertia(fn ($page) => $page->where('push.public_key', 'chave-publica'));
    }
}
