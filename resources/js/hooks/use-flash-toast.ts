import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;
            const badges = flash?.badges as
                | { label: string; emoji: string }[]
                | undefined;

            // Selo novo: comemoração discreta, só para a própria pessoa.
            badges?.forEach((badge) =>
                toast.success(`${badge.emoji} Novo selo: ${badge.label}!`, {
                    duration: 6000,
                }),
            );

            if (!data) {
                return;
            }

            const { action } = data;

            toast[data.type](
                data.message,
                action
                    ? {
                          duration: 10000,
                          action: {
                              label: action.label,
                              onClick: () => router.post(action.url),
                          },
                      }
                    : undefined,
            );
        });
    }, []);
}
