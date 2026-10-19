import { useEffect, useRef, useState } from 'react';

export type AutosaveStatus = 'idle' | 'saving' | 'saved' | 'error';

type Callbacks = {
    onSuccess: () => void;
    onError: () => void;
    onHttpException: () => false;
    onNetworkError: () => false;
};

/**
 * Salva o texto sozinho depois de uma pausa na digitação. Devolve o estado
 * para a linha "Salvando… / Salvo" e um `retry` para quando falhar.
 *
 * Sem rede (ou com erro do servidor) o Inertia não chama `onError`, que é só
 * para validação: sem os outros dois callbacks a linha ficava em "Salvando…"
 * para sempre. Quando a rede volta, tenta de novo sozinho; e, enquanto houver
 * texto não salvo, o navegador avisa antes de sair da página.
 */
export function useAutosave(
    value: string,
    initial: string,
    save: (value: string, callbacks: Callbacks) => void,
    delay = 1200,
) {
    const [status, setStatus] = useState<AutosaveStatus>('idle');
    const [attempt, setAttempt] = useState(0);
    const last = useRef(initial);
    const saveRef = useRef(save);

    useEffect(() => {
        saveRef.current = save;
    });

    useEffect(() => {
        if (value === last.current) {
            return;
        }

        setStatus('idle');

        const timer = setTimeout(() => {
            setStatus('saving');
            saveRef.current(value, {
                onSuccess: () => {
                    last.current = value;
                    setStatus('saved');
                },
                onError: () => setStatus('error'),
                // false: a linha de status já mostra o erro, sem o aviso padrão.
                onHttpException: () => {
                    setStatus('error');

                    return false;
                },
                onNetworkError: () => {
                    setStatus('error');

                    return false;
                },
            });
        }, delay);

        return () => clearTimeout(timer);
    }, [value, attempt, delay]);

    useEffect(() => {
        if (status !== 'error') {
            return;
        }

        const onOnline = () => setAttempt((n) => n + 1);
        window.addEventListener('online', onOnline);

        return () => window.removeEventListener('online', onOnline);
    }, [status]);

    const unsaved = value !== last.current;

    useEffect(() => {
        if (!unsaved) {
            return;
        }

        const onBeforeUnload = (event: BeforeUnloadEvent) =>
            event.preventDefault();
        window.addEventListener('beforeunload', onBeforeUnload);

        return () => window.removeEventListener('beforeunload', onBeforeUnload);
    }, [unsaved]);

    return { status, retry: () => setAttempt((n) => n + 1) };
}
