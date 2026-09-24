import { Check, CircleCheck } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { LessonReading } from '@/types';

export type ReadingTracking = {
    /** Dias do plano (1 = segunda ... 7 = domingo) já marcados como lidos. */
    checkedWeekdays: number[];
    onToggle: (reading: LessonReading, done: boolean) => void;
};

/**
 * Plano de leitura da semana. A leitura de hoje fica em destaque e, para
 * membros da classe, qualquer dia pode ser marcado como lido a qualquer
 * momento (dá para adiantar ou pôr a leitura em dia).
 */
export function ReadingPlan({
    readings,
    tracking,
}: {
    readings: LessonReading[];
    tracking?: ReadingTracking;
}) {
    return (
        <ol className="space-y-2.5">
            {readings.map((reading) => {
                const weekday = reading.weekday;
                const done =
                    !!tracking &&
                    weekday !== null &&
                    tracking.checkedWeekdays.includes(weekday);

                return (
                    <li
                        key={reading.id}
                        className={cn(
                            'grid grid-cols-[3rem_1fr] items-center gap-x-3.5 gap-y-3 rounded-2xl border bg-card p-3.5 sm:grid-cols-[3rem_1fr_auto]',
                            reading.is_today &&
                                'border-primary/50 bg-accent/60 ring-1 ring-primary/20',
                        )}
                    >
                        <span
                            className={cn(
                                'flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-muted text-xs font-semibold text-muted-foreground uppercase',
                                reading.is_today &&
                                    'bg-primary text-primary-foreground',
                                done &&
                                    'bg-emerald-600 text-white dark:bg-emerald-700',
                            )}
                        >
                            {done ? (
                                <Check className="size-5" />
                            ) : (
                                (reading.weekday_short ?? '•')
                            )}
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="text-xs font-medium text-muted-foreground">
                                {reading.is_today
                                    ? 'Leitura de hoje'
                                    : (reading.weekday_label ?? 'Leitura')}
                            </p>
                            <p className="font-serif text-lg font-semibold">
                                {reading.reference}
                            </p>
                            {reading.notes && (
                                <p className="mt-0.5 text-sm text-pretty text-muted-foreground">
                                    {reading.notes}
                                </p>
                            )}
                        </div>
                        {tracking && weekday !== null && (
                            <button
                                type="button"
                                onClick={() => tracking.onToggle(reading, done)}
                                aria-pressed={done}
                                className={cn(
                                    'col-start-2 flex min-h-11 items-center gap-1.5 justify-self-start rounded-xl border px-3 text-sm font-medium sm:col-start-auto',
                                    done
                                        ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400'
                                        : reading.is_today
                                          ? 'bg-primary text-primary-foreground'
                                          : 'bg-card text-foreground hover:bg-muted',
                                )}
                            >
                                <CircleCheck className="size-4" />
                                {done ? 'Lido' : 'Marcar como lido'}
                            </button>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
