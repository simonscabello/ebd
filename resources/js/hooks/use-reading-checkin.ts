import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    destroy as undoCheckin,
    store as storeCheckin,
} from '@/routes/lessons/checkins';

/**
 * Marca/desmarca a leitura de um dia da semana, com estado de "salvando" por
 * dia (o toque no celular precisa de resposta imediata) e aviso em caso de erro.
 */
export function useReadingCheckin(lessonSlug: string) {
    const [pending, setPending] = useState<number[]>([]);

    const toggle = (
        weekday: number,
        done: boolean,
        readingId: number | null = null,
    ) => {
        if (pending.includes(weekday)) {
            return;
        }

        const options = {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setPending((current) => [...current, weekday]),
            onFinish: () =>
                setPending((current) =>
                    current.filter((day) => day !== weekday),
                ),
            onError: () =>
                toast.error(
                    'Não foi possível salvar a leitura. Tente de novo.',
                ),
        };

        if (done) {
            router.delete(undoCheckin.url(lessonSlug), {
                data: { weekday },
                ...options,
            });
        } else {
            router.post(
                storeCheckin.url(lessonSlug),
                { weekday, reading_id: readingId },
                options,
            );
        }
    };

    return {
        toggle,
        isPending: (weekday: number) => pending.includes(weekday),
    };
}
