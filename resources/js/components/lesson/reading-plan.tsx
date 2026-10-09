import { BookOpen, Check, CircleCheck } from 'lucide-react';
import { useState } from 'react';
import { ReadingSheet } from '@/components/lesson/reading-sheet';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { LessonReading } from '@/types';

export type ReadingTracking = {
    /** Dias do plano (1 = segunda ... 7 = domingo) já marcados como lidos. */
    checkedWeekdays: number[];
    /** Dias cuja marcação está sendo salva. */
    pendingWeekdays?: number[];
    onToggle: (reading: LessonReading, done: boolean) => void;
};

/**
 * Plano de leitura da semana. Cada dia é uma linha curta (dia, referência e
 * "Ler o texto"); a apresentação, o texto bíblico e o "Marcar como lido" ficam
 * no leitor que abre. A leitura de hoje fica em destaque e, para membros da
 * classe, qualquer dia pode ser marcado a qualquer momento (adiantar ou pôr
 * a leitura em dia).
 */
export function ReadingPlan({
    readings,
    tracking,
}: {
    readings: LessonReading[];
    tracking?: ReadingTracking;
}) {
    const [openId, setOpenId] = useState<number | null>(null);
    const active = readings.find((r) => r.id === openId) ?? null;
    const activeDone =
        !!tracking &&
        active?.weekday != null &&
        tracking.checkedWeekdays.includes(active.weekday);

    return (
        <>
            <ReadingSheet
                reading={active}
                open={active !== null}
                onOpenChange={(open) => !open && setOpenId(null)}
                footer={
                    tracking && active && active.weekday !== null ? (
                        <ReadToggle
                            done={activeDone}
                            pending={(tracking.pendingWeekdays ?? []).includes(
                                active.weekday,
                            )}
                            highlight
                            onClick={() =>
                                tracking.onToggle(active, activeDone)
                            }
                            className="w-full justify-center"
                        />
                    ) : undefined
                }
            />
            <ol className="space-y-2.5">
                {readings.map((reading) => {
                    const weekday = reading.weekday;
                    const done =
                        !!tracking &&
                        weekday !== null &&
                        tracking.checkedWeekdays.includes(weekday);

                    return (
                        <li key={reading.id}>
                            <ReadingRow
                                badge={reading.weekday_short ?? '•'}
                                label={
                                    reading.is_today
                                        ? 'Leitura de hoje'
                                        : (reading.weekday_label ?? 'Leitura')
                                }
                                reference={reading.reference}
                                isToday={reading.is_today}
                                done={done}
                                onRead={() => setOpenId(reading.id)}
                            />
                        </li>
                    );
                })}
            </ol>
        </>
    );
}

/**
 * Botão "Marcar como lido" / "Lido", com estado de salvamento.
 */
export function ReadToggle({
    done,
    pending = false,
    highlight = false,
    onClick,
    className,
}: {
    done: boolean;
    pending?: boolean;
    highlight?: boolean;
    onClick: () => void;
    className?: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={done}
            aria-busy={pending || undefined}
            disabled={pending}
            className={cn(
                'flex min-h-11 items-center gap-1.5 rounded-xl border px-3 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-70',
                done
                    ? 'border-success text-success-foreground hover:bg-success/10'
                    : highlight
                      ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90'
                      : 'bg-card text-foreground hover:bg-muted',
                className,
            )}
        >
            {pending ? <Spinner /> : <CircleCheck className="size-4" />}
            {pending ? 'Salvando…' : done ? 'Lido' : 'Marcar como lido'}
        </button>
    );
}

/**
 * Uma leitura em linha: o dia como selo, a referência e o botão "Ler o
 * texto". Usada na página da lição, em "Leituras da semana" e no Início.
 */
export function ReadingRow({
    badge,
    label,
    reference,
    isToday = false,
    done = false,
    onRead,
    className,
}: {
    badge: string;
    label: string;
    reference: string;
    isToday?: boolean;
    done?: boolean;
    onRead: () => void;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex items-center gap-3 rounded-2xl border bg-card p-3 transition-colors',
                isToday &&
                    !done &&
                    'border-primary/50 bg-accent/60 ring-1 ring-primary/20',
                done && 'border-success/40 bg-success-soft',
                className,
            )}
        >
            <span
                className={cn(
                    'flex size-11 shrink-0 items-center justify-center rounded-xl bg-muted text-xs font-semibold text-muted-foreground uppercase',
                    isToday && 'bg-primary text-primary-foreground',
                    done && 'bg-success text-white',
                )}
            >
                {done ? <Check className="size-5" /> : badge}
            </span>
            <div className="min-w-0 flex-1">
                <p
                    className={cn(
                        'text-xs font-medium text-muted-foreground',
                        done && 'text-success-foreground',
                    )}
                >
                    {done ? `${label} · lida` : label}
                </p>
                <p className="font-serif text-lg leading-snug font-semibold">
                    {reference}
                </p>
            </div>
            <button
                type="button"
                onClick={onRead}
                className={cn(
                    'inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-xl border px-3 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                    isToday && !done
                        ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90'
                        : 'bg-card hover:bg-muted',
                )}
            >
                <BookOpen className="size-4" />
                Ler o texto
            </button>
        </div>
    );
}
