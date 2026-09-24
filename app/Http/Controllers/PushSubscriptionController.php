<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Inscrição do aparelho para receber lembretes (Web Push). O navegador manda
 * a inscrição que criou; o endpoint é único, então repetir só atualiza.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'in:aes128gcm,aesgcm'],
        ]);

        PushSubscription::query()->updateOrCreate(['endpoint' => $data['endpoint']], [
            'user_id' => $user->id,
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

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
