<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Push\PushMessage;
use App\Support\Push\PushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Inscrição do aparelho para receber lembretes (Web Push). O navegador manda
 * a inscrição que criou; o endpoint é único, então repetir só atualiza.
 */
class PushSubscriptionController extends Controller
{
    private const RULES = [
        'endpoint' => ['required', 'string', 'url', 'max:500'],
        'keys.p256dh' => ['required', 'string', 'max:255'],
        'keys.auth' => ['required', 'string', 'max:255'],
        'content_encoding' => ['nullable', 'string', 'in:aes128gcm,aesgcm'],
    ];

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        self::save($request, $user->id, $request->validate(self::RULES));

        return back();
    }

    /**
     * Chave pública VAPID, para o service worker se inscrever de novo sem a página.
     */
    public function key(): JsonResponse
    {
        return response()->json(['public_key' => config('ebd.push.public_key') ?: null]);
    }

    /**
     * O navegador trocou a inscrição do aparelho (pushsubscriptionchange). A
     * nova fica com o dono da antiga ou, se o navegador não informou a antiga,
     * com quem está logado. Sem dono conhecido, não há o que fazer.
     */
    public function renew(Request $request): Response
    {
        $data = $request->validate([
            ...self::RULES,
            'old_endpoint' => ['nullable', 'string', 'max:500'],
        ]);

        $old = filled($data['old_endpoint'] ?? null)
            ? PushSubscription::query()->where('endpoint', $data['old_endpoint'])->first()
            : null;

        $userId = $old->user_id ?? $request->user()?->id;

        if ($userId === null) {
            return response()->noContent();
        }

        self::save($request, $userId, $data);

        if ($old !== null && $old->endpoint !== $data['endpoint']) {
            $old->delete();
        }

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function save(Request $request, int $userId, array $data): void
    {
        PushSubscription::query()->updateOrCreate(['endpoint' => $data['endpoint']], [
            'user_id' => $userId,
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
    }

    /**
     * Manda uma notificação só para os aparelhos de quem pediu, para a pessoa
     * confirmar que está tudo ligado sem depender de um horário ou de outra conta.
     */
    public function test(Request $request, PushSender $sender): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $devices = $user->pushSubscriptions()->get();

        if ($devices->isEmpty()) {
            $this->toast('Nenhum aparelho inscrito nesta conta.', 'error');

            return back();
        }

        try {
            $sent = $sender->send($devices, new PushMessage(
                title: 'Os lembretes estão funcionando!',
                body: "Oi, {$user->name}. É assim que a leitura do dia vai chegar.",
                url: route('home'),
                tag: 'test',
            ));
        } catch (\Throwable $e) {
            report($e);
            $sent = 0;
        }

        $this->toast($sent > 0
            ? "Notificação de teste enviada para {$sent} aparelho(s)."
            : 'O serviço de push não aceitou o envio. Tente desativar e ativar de novo.', $sent > 0 ? 'success' : 'error');

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate(['endpoint' => ['required', 'string', 'max:500']]);

        $user->pushSubscriptions()->where('endpoint', $data['endpoint'])->delete();

        return back();
    }
}
