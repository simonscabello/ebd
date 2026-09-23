/**
 * PWA: registro do service worker e instalação do app no celular.
 */

type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

export type Platform = 'ios' | 'android' | 'other';

let deferredPrompt: BeforeInstallPromptEvent | null = null;
const listeners = new Set<() => void>();

/**
 * Registra o service worker (public/sw.js) apenas no build de produção.
 * Em desenvolvimento ele atrapalharia o hot reload do Vite.
 */
export function registerServiceWorker(): void {
    if (typeof window === 'undefined') {
        return;
    }

    // Android/Chrome: guarda o convite de instalação para usar num botão nosso.
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event as BeforeInstallPromptEvent;
        listeners.forEach((listener) => listener());
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        listeners.forEach((listener) => listener());
    });

    if (!import.meta.env.PROD || !('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Sem service worker o app continua funcionando normalmente.
        });
    });
}

/** O navegador ofereceu o convite nativo de instalação (Android/Chrome). */
export function canPromptInstall(): boolean {
    return deferredPrompt !== null;
}

/** Abre o diálogo nativo de instalação. Devolve true se a pessoa aceitou. */
export async function promptInstall(): Promise<boolean> {
    if (!deferredPrompt) {
        return false;
    }

    const event = deferredPrompt;
    deferredPrompt = null;
    await event.prompt();
    const { outcome } = await event.userChoice;
    listeners.forEach((listener) => listener());

    return outcome === 'accepted';
}

/** Avisa quando o convite de instalação aparece ou é usado. */
export function onInstallAvailabilityChange(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/** Já está aberto como app instalado (tela de início). */
export function isStandalone(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return (
        window.matchMedia?.('(display-mode: standalone)').matches ||
        (navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

export function detectPlatform(): Platform {
    if (typeof navigator === 'undefined') {
        return 'other';
    }

    const ua = navigator.userAgent;

    // iPadOS se apresenta como Mac, mas tem tela de toque.
    if (
        /iPhone|iPad|iPod/i.test(ua) ||
        (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1)
    ) {
        return 'ios';
    }

    if (/Android/i.test(ua)) {
        return 'android';
    }

    return 'other';
}
