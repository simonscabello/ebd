/*
 * Service worker básico do EBD.
 *
 * - Assets versionados do Vite (/build/*) e ícones: cache-first.
 * - Navegação (páginas): sempre pela rede; sem conexão, mostra /offline.html.
 *   Páginas HTML não são guardadas em cache para não exibir conteúdo
 *   desatualizado nem dados de uma sessão autenticada.
 * - Push: mostra a notificação enviada pelo servidor e, ao tocar, abre a
 *   página indicada (reaproveitando uma janela do app, se houver).
 */
const VERSION = 'ebd-v4';
const STATIC_CACHE = `${VERSION}-static`;
const PRECACHE = [
    '/offline.html',
    '/icons/icon.svg',
    '/icons/icon-192.png',
    '/icons/badge-96.png',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => !key.startsWith(VERSION))
                        .map((key) => caches.delete(key)),
                ),
            ),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html')),
        );

        return;
    }

    if (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icons/')
    ) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches
                                .open(STATIC_CACHE)
                                .then((cache) => cache.put(request, copy));
                        }

                        return response;
                    }),
            ),
        );
    }
});

self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { body: event.data ? event.data.text() : '' };
    }

    const options = {
        body: data.body || '',
        icon: '/icons/icon-192.png',
        badge: '/icons/badge-96.png',
        tag: data.tag || undefined,
        renotify: Boolean(data.tag),
        data: { url: data.url || '/' },
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'EBD', options),
    );
});

/*
 * O navegador pode trocar (ou expirar) a inscrição sozinho. Sem isto o
 * aparelho para de receber lembretes em silêncio e o botão aparece desligado.
 * Inscreve de novo e avisa o servidor, que liga a nova ao dono da antiga.
 */
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(
        renewSubscription(event.oldSubscription, event.newSubscription),
    );
});

async function renewSubscription(oldSubscription, newSubscription) {
    let subscription = newSubscription;

    if (!subscription) {
        const key =
            (oldSubscription &&
                oldSubscription.options &&
                oldSubscription.options.applicationServerKey) ||
            (await fetchPublicKey());

        if (!key) {
            return;
        }

        subscription = await self.registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: key,
        });
    }

    const json = subscription.toJSON();
    const encodings =
        self.PushManager && self.PushManager.supportedContentEncodings;

    await fetch('/notificacoes/renovacao', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
        },
        body: JSON.stringify({
            old_endpoint: oldSubscription ? oldSubscription.endpoint : null,
            endpoint: subscription.endpoint,
            keys: json.keys,
            content_encoding:
                encodings && !encodings.includes('aes128gcm')
                    ? 'aesgcm'
                    : 'aes128gcm',
        }),
    });
}

async function fetchPublicKey() {
    try {
        const response = await fetch('/notificacoes/chave', {
            headers: { Accept: 'application/json' },
        });

        return response.ok ? (await response.json()).public_key : null;
    } catch {
        return null;
    }
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = new URL(
        (event.notification.data && event.notification.data.url) || '/',
        self.location.origin,
    ).href;

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clients) => {
                const open = clients.find(
                    (client) =>
                        new URL(client.url).origin === self.location.origin,
                );

                if (open) {
                    return (
                        'navigate' in open
                            ? open.navigate(url)
                            : Promise.resolve(open)
                    ).then((client) => (client || open).focus());
                }

                return self.clients.openWindow(url);
            }),
    );
});
