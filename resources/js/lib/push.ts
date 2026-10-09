/**
 * Web Push no PWA: inscrição do aparelho nos lembretes.
 *
 * Só funciona com o service worker registrado (build de produção). No iPhone
 * o navegador só oferece push quando o app está instalado na tela inicial.
 */

export type SerializedSubscription = {
    endpoint: string;
    keys: { p256dh: string; auth: string };
    content_encoding: 'aes128gcm' | 'aesgcm';
};

export function pushSupported(): boolean {
    return (
        typeof window !== 'undefined' &&
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window
    );
}

async function registration(): Promise<ServiceWorkerRegistration | null> {
    if (!pushSupported()) {
        return null;
    }

    return (await navigator.serviceWorker.getRegistration()) ?? null;
}

/** Há service worker ativo neste aparelho (sem ele não existe push). */
export async function pushAvailable(): Promise<boolean> {
    return (await registration()) !== null;
}

export async function currentSubscription(): Promise<PushSubscription | null> {
    const reg = await registration();

    return reg ? reg.pushManager.getSubscription() : null;
}

export class PermissionDeniedError extends Error {}

export async function subscribeToPush(
    publicKey: string,
): Promise<PushSubscription> {
    const reg = await registration();

    if (!reg) {
        throw new Error('Service worker não registrado.');
    }

    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        throw new PermissionDeniedError('Permissão negada.');
    }

    return (
        (await reg.pushManager.getSubscription()) ??
        reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: publicKey,
        })
    );
}

export async function unsubscribeFromPush(): Promise<PushSubscription | null> {
    const subscription = await currentSubscription();

    if (subscription) {
        await subscription.unsubscribe();
    }

    return subscription;
}

export function serializeSubscription(
    subscription: PushSubscription,
): SerializedSubscription {
    const json = subscription.toJSON();
    const encodings = (
        PushManager as unknown as { supportedContentEncodings?: string[] }
    ).supportedContentEncodings;

    return {
        endpoint: subscription.endpoint,
        keys: { p256dh: json.keys?.p256dh ?? '', auth: json.keys?.auth ?? '' },
        content_encoding: encodings?.includes('aes128gcm')
            ? 'aes128gcm'
            : 'aesgcm',
    };
}
