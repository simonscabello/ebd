import { cn } from '@/lib/utils';
import type { LessonReading } from '@/types';

/**
 * Plano de leitura da semana. A leitura de hoje fica em destaque.
 */
export function ReadingPlan({ readings }: { readings: LessonReading[] }) {
    return (
        <ol className="space-y-2.5">
            {readings.map((reading) => (
                <li
                    key={reading.id}
                    className={cn(
                        'flex gap-3.5 rounded-2xl border bg-card p-3.5',
                        reading.is_today &&
                            'border-primary/50 bg-accent/60 ring-1 ring-primary/20',
                    )}
                >
                    <span
                        className={cn(
                            'flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-muted text-xs font-semibold text-muted-foreground uppercase',
                            reading.is_today &&
                                'bg-primary text-primary-foreground',
                        )}
                    >
                        {reading.weekday_short ?? '•'}
                    </span>
                    <div className="min-w-0">
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
                </li>
            ))}
        </ol>
    );
}
