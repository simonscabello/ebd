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
const VERSION = 'ebd-v3';
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
