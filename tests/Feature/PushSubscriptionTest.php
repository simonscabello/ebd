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
    private function payload(string $endpoint = 'https://push.example/abc'): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-key'], 'content_encoding' => 'aes128gcm'];
    }

    public function test_a_logged_in_person_subscribes_and_unsubscribes_their_device(): void
    {
        $user = User::factory()->studentOf(Classroom::factory()->create())->create();

        $this->actingAs($user)->post('/notificacoes/inscricao', $this->payload())->assertRedirect();

        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $user->id, 'endpoint' => 'https://push.example/abc', 'public_key' => 'p256dh-key']);

        // Repetir só atualiza.
        $this->actingAs($user)->post('/notificacoes/inscricao', [...$this->payload(), 'keys' => ['p256dh' => 'nova', 'auth' => 'auth-key']]);
        $this->assertSame(1, PushSubscription::query()->count());
        $this->assertSame('nova', PushSubscription::query()->sole()->public_key);

        $this->actingAs($user)->delete('/notificacoes/inscricao', ['endpoint' => 'https://push.example/abc'])->assertRedirect();
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
        $this->actingAs($first)->delete('/notificacoes/inscricao', ['endpoint' => 'https://push.example/abc']);
        $this->assertSame(1, PushSubscription::query()->count());
    }

    public function test_validation_and_authentication(): void
    {
        $this->post('/notificacoes/inscricao', $this->payload())->assertRedirect(route('login'));

        $user = User::factory()->create();

        $this->actingAs($user)->post('/notificacoes/inscricao', ['endpoint' => 'não é url'])
            ->assertSessionHasErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
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

    public function test_the_public_key_is_shared_with_the_pages(): void
    {
        config(['ebd.push.public_key' => 'chave-publica']);

        $this->get('/')->assertInertia(fn ($page) => $page->where('push.public_key', 'chave-publica'));
    }
}
