import { useEffect, useRef, useState } from 'react';

export type AutosaveStatus = 'idle' | 'saving' | 'saved' | 'error';

type Callbacks = { onSuccess: () => void; onError: () => void };

/**
 * Salva o texto sozinho depois de uma pausa na digitação. Devolve o estado
 * para a linha "Salvando… / Salvo" e um `retry` para quando falhar.
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
            });
        }, delay);

        return () => clearTimeout(timer);
    }, [value, attempt, delay]);

    return { status, retry: () => setAttempt((n) => n + 1) };
}
