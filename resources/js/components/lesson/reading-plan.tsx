import { Check, CircleCheck } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { LessonReading } from '@/types';

export type ReadingTracking = {
    /** Datas (Y-m-d) em que a pessoa marcou "Li". */
    checkins: string[];
    /** Hoje (Y-m-d) no fuso da igreja. */
    today: string;
    onCheck: (reading: LessonReading, yesterday: boolean) => void;
    onUndo: (date: string) => void;
};

/**
 * Data desta semana (segunda a domingo) para um dia ISO (1 = segunda).
 */
export function dateOfWeekday(today: string, weekday: number): string {
    const base = new Date(`${today}T00:00:00Z`);
    const isoToday = base.getUTCDay() === 0 ? 7 : base.getUTCDay();
    base.setUTCDate(base.getUTCDate() + (weekday - isoToday));

    return base.toISOString().slice(0, 10);
}

function previousDay(date: string): string {
    const day = new Date(`${date}T00:00:00Z`);
    day.setUTCDate(day.getUTCDate() - 1);

    return day.toISOString().slice(0, 10);
}

/**
 * Plano de leitura da semana. A leitura de hoje fica em destaque e, para
 * membros da classe, pode ser marcada como lida ("Li hoje").
 */
export function ReadingPlan({
    readings,
    tracking,
}: {
    readings: LessonReading[];
    tracking?: ReadingTracking;
}) {
    const yesterday = tracking ? previousDay(tracking.today) : null;

    return (
        <ol className="space-y-2.5">
            {readings.map((reading) => {
                const date =
                    tracking && reading.weekday
                        ? dateOfWeekday(tracking.today, reading.weekday)
                        : null;
                const done = !!date && !!tracking?.checkins.includes(date);
                const canMark =
                    !!tracking &&
                    (date === tracking.today || date === yesterday);

                return (
                    <li
                        key={reading.id}
                        className={cn(
                            'flex items-center gap-3.5 rounded-2xl border bg-card p-3.5',
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
                        {canMark && date && tracking && (
                            <button
                                type="button"
                                onClick={() =>
                                    done
                                        ? tracking.onUndo(date)
                                        : tracking.onCheck(
                                              reading,
                                              date === yesterday,
                                          )
                                }
                                aria-pressed={done}
                                className={cn(
                                    'flex min-h-11 shrink-0 items-center gap-1.5 rounded-xl border px-3 text-sm font-medium',
                                    done
                                        ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400'
                                        : 'bg-primary text-primary-foreground',
                                )}
                            >
                                <CircleCheck className="size-4" />
                                {done
                                    ? 'Lido'
                                    : date === yesterday
                                      ? 'Li ontem'
                                      : 'Li hoje'}
                            </button>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
