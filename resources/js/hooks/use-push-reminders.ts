import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
    currentSubscription,
    PermissionDeniedError,
    pushAvailable,
    pushSupported,
    serializeSubscription,
    subscribeToPush,
    unsubscribeFromPush,
} from '@/lib/push';
import { destroy, store } from '@/routes/push';

export type ReminderStatus =
    /** Ainda consultando o navegador. */
    | 'checking'
    /** Navegador sem push (ou app não instalado no iPhone). */
    | 'unsupported'
    /** A pessoa bloqueou as notificações do site. */
    | 'denied'
    | 'off'
    | 'on';

/**
 * Estado dos lembretes neste aparelho e as ações de ativar/desativar.
 * Ao encontrar uma inscrição já existente, reenvia ao servidor uma vez por
 * sessão: garante que ela está ligada a quem está logado agora.
 */
export function usePushReminders() {
    const { push, auth } = usePage().props;
    const publicKey = push.public_key;
    const userId = auth.user?.id;
    const [status, setStatus] = useState<ReminderStatus>('checking');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        let active = true;

        void (async () => {
            if (!publicKey || !pushSupported() || !(await pushAvailable())) {
                return active && setStatus('unsupported');
            }

            if (Notification.permission === 'denied') {
                return active && setStatus('denied');
            }

            const subscription = await currentSubscription();

            if (!active) {
                return;
            }

            setStatus(subscription ? 'on' : 'off');

            if (subscription && userId) {
                syncOnce(userId, serializeSubscription(subscription));
            }
        })();

        return () => {
            active = false;
        };
    }, [publicKey, userId]);

    const enable = useCallback(async () => {
        if (!publicKey || busy) {
            return;
        }

        setBusy(true);

        try {
            const subscription = await subscribeToPush(publicKey);
            await save(serializeSubscription(subscription));
            setStatus('on');
            toast.success('Lembretes ativados neste aparelho.');
        } catch (error) {
            if (error instanceof PermissionDeniedError) {
                setStatus(
                    Notification.permission === 'denied' ? 'denied' : 'off',
                );
            } else {
                toast.error('Não foi possível ativar os lembretes.');
            }
        } finally {
            setBusy(false);
        }
    }, [publicKey, busy]);

    const disable = useCallback(async () => {
        if (busy) {
            return;
        }

        setBusy(true);

        try {
            const subscription = await unsubscribeFromPush();

            if (subscription) {
                await remove(subscription.endpoint);
            }

            setStatus('off');
            toast.success('Lembretes desativados neste aparelho.');
        } catch {
            toast.error('Não foi possível desativar os lembretes.');
        } finally {
            setBusy(false);
        }
    }, [busy]);

    return { status, busy, enable, disable };
}

function save(subscription: ReturnType<typeof serializeSubscription>) {
    return new Promise<void>((resolve, reject) => {
        router.post(store.url(), subscription, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => resolve(),
            onError: () => reject(new Error('save')),
        });
    });
}

function remove(endpoint: string) {
    return new Promise<void>((resolve, reject) => {
        router.delete(destroy.url(), {
            data: { endpoint },
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => resolve(),
            onError: () => reject(new Error('remove')),
        });
    });
}

function syncOnce(
    userId: number,
    subscription: ReturnType<typeof serializeSubscription>,
) {
    const key = `ebd:push-synced:${userId}`;

    try {
        if (window.sessionStorage.getItem(key)) {
            return;
        }

        window.sessionStorage.setItem(key, '1');
    } catch {
        // Sem sessionStorage: sincroniza a cada visita, sem prejuízo.
    }

    save(subscription).catch(() => {
        // Silencioso: a próxima visita tenta de novo.
    });
}
