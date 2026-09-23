/**
 * Registra o service worker (public/sw.js) apenas no build de produção.
 * Em desenvolvimento ele atrapalharia o hot reload do Vite.
 */
export function registerServiceWorker(): void {
    if (
        !import.meta.env.PROD ||
        typeof window === 'undefined' ||
        !('serviceWorker' in navigator)
    ) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Sem service worker o app continua funcionando normalmente.
        });
    });
}
