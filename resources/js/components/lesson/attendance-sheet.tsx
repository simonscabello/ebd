import { router } from '@inertiajs/react';
import { Check, Minus, Plus, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import { update } from '@/routes/admin/meetings/attendance';

export type Roster = { id: number; name: string }[];

/**
 * Chamada: toque no nome de quem está presente. Salva sozinha (a lista
 * inteira é reenviada, então Wi-Fi instável não duplica nada).
 */
export function AttendanceSheet({
    meetingId,
    roster,
    initialPresent,
    initialVisitors,
    onChange,
}: {
    meetingId: number;
    roster: Roster;
    initialPresent: number[];
    initialVisitors: number;
    /** Avisa quantas pessoas estão marcadas (para o resumo fora do painel). */
    onChange?: (present: number) => void;
}) {
    const [present, setPresent] = useState<number[]>(initialPresent);
    const [visitors, setVisitors] = useState(initialVisitors);
    const [status, setStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>(
        'idle',
    );
    const touched = useRef(false);

    useEffect(() => {
        onChange?.(present.length);
    }, [present.length, onChange]);

    useEffect(() => {
        if (!touched.current) {
            return;
        }

        const timer = setTimeout(() => {
            setStatus('saving');
            router.put(
                update.url(meetingId),
                { present, visitors },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['conduct'],
                    onSuccess: () => setStatus('saved'),
                    onError: () => setStatus('error'),
                },
            );
        }, 800);

        return () => clearTimeout(timer);
    }, [present, visitors, meetingId]);

    const toggle = (id: number) => {
        touched.current = true;
        setPresent((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id],
        );
    };

    const changeVisitors = (delta: number) => {
        touched.current = true;
        setVisitors((v) => Math.max(0, v + delta));
    };

    return (
        <section>
            <div className="mb-3 flex items-center justify-between gap-3">
                <h2 className="flex items-center gap-2 text-[0.8em] font-semibold tracking-wide text-muted-foreground uppercase">
                    <Users className="size-4" /> Presentes · {present.length}/
                    {roster.length}
                </h2>
                <span
                    className={cn(
                        'text-[0.75em] text-muted-foreground',
                        status === 'error' && 'font-medium text-destructive',
                        status === 'saved' && 'text-success-foreground',
                    )}
                    aria-live="polite"
                >
                    {status === 'saving' && 'Salvando…'}
                    {status === 'saved' && 'Salvo'}
                    {status === 'error' && 'Não salvou. Tente de novo.'}
                </span>
            </div>
            {roster.length === 0 ? (
                <p className="text-[0.85em] text-muted-foreground">
                    Nenhum aluno cadastrado na classe ainda.
                </p>
            ) : (
                <ul className="grid gap-2 sm:grid-cols-2">
                    {roster.map((student) => {
                        const isPresent = present.includes(student.id);

                        return (
                            <li key={student.id}>
                                <button
                                    type="button"
                                    onClick={() => toggle(student.id)}
                                    aria-pressed={isPresent}
                                    className={cn(
                                        'flex min-h-12 w-full items-center gap-3 rounded-xl border bg-card px-3 text-left text-[0.9em] transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        isPresent &&
                                            'border-success bg-success-soft',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex size-6 shrink-0 items-center justify-center rounded-full border',
                                            isPresent &&
                                                'border-success bg-success text-white',
                                        )}
                                    >
                                        {isPresent && (
                                            <Check className="size-4" />
                                        )}
                                    </span>
                                    {student.name}
                                </button>
                            </li>
                        );
                    })}
                </ul>
            )}
            <div className="mt-3 flex items-center gap-3 text-[0.85em]">
                <span>Visitantes</span>
                <button
                    type="button"
                    onClick={() => changeVisitors(-1)}
                    className="flex size-10 items-center justify-center rounded-lg border bg-card focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label="Menos um visitante"
                >
                    <Minus className="size-4" />
                </button>
                <span className="w-6 text-center font-semibold">
                    {visitors}
                </span>
                <button
                    type="button"
                    onClick={() => changeVisitors(1)}
                    className="flex size-10 items-center justify-center rounded-lg border bg-card focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label="Mais um visitante"
                >
                    <Plus className="size-4" />
                </button>
            </div>
        </section>
    );
}
