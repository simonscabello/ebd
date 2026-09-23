import { router } from '@inertiajs/react';
import { Check, Lock } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Textarea } from '@/components/ui/textarea';
import { update } from '@/routes/lessons/note';

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
    const [status, setStatus] = useState<'idle' | 'saving' | 'saved'>('idle');
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
                    onError: () => setStatus('idle'),
                },
            );
        }, 1200);

        return () => clearTimeout(timer);
    }, [body, lessonSlug]);

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
                className="font-serif text-base"
            />
            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                <Lock className="size-3.5" /> Só você vê esta anotação.
                {status === 'saving' && <span>Salvando…</span>}
                {status === 'saved' && (
                    <span className="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-400">
                        <Check className="size-3.5" /> Salvo
                    </span>
                )}
            </p>
        </div>
    );
}
