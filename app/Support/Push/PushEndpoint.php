<?php

namespace App\Support\Push;

use Closure;

/**
 * Endpoints de push aceitos: só https e só os serviços de push dos navegadores
 * (Chrome/Android/Edge-Chromium/Samsung/Opera usam o FCM; Firefox, o da
 * Mozilla; Safari, o da Apple; Edge no Windows, o WNS). O servidor faz POST
 * no endpoint, então um endpoint qualquer seria um jeito de fazê-lo bater num
 * endereço escolhido por quem se inscreveu.
 */
final class PushEndpoint
{
    /** Hosts exatos. */
    private const HOSTS = ['fcm.googleapis.com', 'updates.push.services.mozilla.com'];

    /** Sufixos de host (subdomínios). */
    private const SUFFIXES = ['.push.apple.com', '.notify.windows.com'];

    public static function isAllowed(string $endpoint): bool
    {
        $parts = parse_url($endpoint);

        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['port'])) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');

        return in_array($host, self::HOSTS, true)
            || array_any(self::SUFFIXES, fn (string $suffix) => str_ends_with($host, $suffix));
    }

    /**
     * Regra de validação para o campo do endpoint.
     *
     * @return Closure(string, mixed, Closure(string): mixed): void
     */
    public static function rule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! self::isAllowed($value)) {
                $fail('Este navegador usa um serviço de notificações que não reconhecemos.');
            }
        };
    }
}
