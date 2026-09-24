import { router } from '@inertiajs/react';
import { AlertCircle, Check, Lock } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { update } from '@/routes/lessons/note';

type Status = 'idle' | 'saving' | 'saved' | 'error';

/**
 * Anotação pessoal na lição, salva automaticamente. Só a própria pessoa vê.
 */
export function PersonalNote({
    lessonSlug,
    initial,
}: {
    lessonSlug: string;
    initial: string | null;
}) {
    const [body, setBody] = useState(initial ?? '');
    const [status, setStatus] = useState<Status>('idle');
    const [attempt, setAttempt] = useState(0);
    const last = useRef(initial ?? '');

    useEffect(() => {
        if (body === last.current) {
            return;
        }

        const timer = setTimeout(() => {
            setStatus('saving');
            router.put(
                update.url(lessonSlug),
                { body },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['study'],
                    onSuccess: () => {
                        last.current = body;
                        setStatus('saved');
                    },
                    onError: () => setStatus('error'),
                },
            );
        }, 1200);

        return () => clearTimeout(timer);
    }, [body, lessonSlug, attempt]);

    return (
        <div className="space-y-2">
            <Textarea
                value={body}
                onChange={(event) => {
                    setBody(event.target.value);
                    setStatus('idle');
                }}
                rows={5}
                maxLength={20000}
                placeholder="O que Deus falou com você nesta lição? Dúvidas para levar no domingo…"
                aria-label="Minha anotação"
                aria-describedby="personal-note-status"
                className="font-serif text-base"
            />
            <p
                id="personal-note-status"
                className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                aria-live="polite"
            >
                <span className="inline-flex items-center gap-1.5">
                    <Lock className="size-3.5" /> Só você vê esta anotação.
                </span>
                {status === 'saving' && (
                    <span className="inline-flex items-center gap-1">
                        <Spinner className="size-3.5" /> Salvando…
                    </span>
                )}
                {status === 'saved' && (
                    <span className="inline-flex items-center gap-1 text-success-foreground">
                        <Check className="size-3.5" /> Salvo
                    </span>
                )}
                {status === 'error' && (
                    <span className="inline-flex items-center gap-1 text-destructive">
                        <AlertCircle className="size-3.5" /> Não foi possível
                        salvar.
                        <button
                            type="button"
                            onClick={() => setAttempt((n) => n + 1)}
                            className="font-medium underline underline-offset-2"
                        >
                            Tentar de novo
                        </button>
                    </span>
                )}
            </p>
        </div>
    );
}
