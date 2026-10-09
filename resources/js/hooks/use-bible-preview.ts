import { useEffect, useState } from 'react';
import { preview } from '@/routes/admin/bible';
import type { BiblePassage } from '@/types';

export type BiblePreview =
    | { status: 'idle' }
    | { status: 'loading' }
    | { status: 'found'; passage: BiblePassage }
    | { status: 'missing' };

/**
 * Consulta o texto de uma referência enquanto o professor digita (com atraso
 * para não bater no servidor a cada tecla). "missing" = a referência não foi
 * reconhecida ou o texto bíblico não está importado.
 */
export function useBiblePreview(reference: string): BiblePreview {
    const trimmed = reference.trim();
    const [result, setResult] = useState<{
        reference: string;
        passage: BiblePassage | null;
    } | null>(null);

    useEffect(() => {
        if (trimmed === '') {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            fetch(preview.url({ query: { reference: trimmed } }), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((response) => (response.ok ? response.json() : null))
                .then((data: { passage: BiblePassage | null } | null) => {
                    if (data) {
                        setResult({
                            reference: trimmed,
                            passage: data.passage,
                        });
                    }
                })
                .catch(() => {
                    // Prévia é só ajuda: sem rede, fica em silêncio.
                });
        }, 400);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [trimmed]);

    if (trimmed === '') {
        return { status: 'idle' };
    }

    if (result?.reference !== trimmed) {
        return { status: 'loading' };
    }

    return result.passage
        ? { status: 'found', passage: result.passage }
        : { status: 'missing' };
}
