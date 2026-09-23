import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export type WeekDay = {
    date: string;
    weekday: number;
    short: string;
    is_today: boolean;
    is_future: boolean;
    done: boolean;
};

/**
 * Faixa com os 7 dias da semana: lido, hoje, futuro.
 */
export function WeekBar({ days }: { days: WeekDay[] }) {
    return (
        <ol className="grid grid-cols-7 gap-1.5" aria-label="Dias da semana">
            {days.map((day) => (
                <li key={day.date} className="flex flex-col items-center gap-1">
                    <span
                        className={cn(
                            'flex size-10 items-center justify-center rounded-full border text-xs font-semibold',
                            day.done &&
                                'border-emerald-600 bg-emerald-600 text-white dark:border-emerald-700 dark:bg-emerald-700',
                            !day.done &&
                                day.is_today &&
                                'border-primary text-primary ring-2 ring-primary/30',
                            !day.done &&
                                day.is_future &&
                                'border-dashed text-muted-foreground',
                            !day.done &&
                                !day.is_today &&
                                !day.is_future &&
                                'bg-muted text-muted-foreground',
                        )}
                        aria-label={`${day.short}${day.done ? ': lido' : ''}`}
                    >
                        {day.done ? (
                            <Check className="size-4" />
                        ) : (
                            day.short.slice(0, 1)
                        )}
                    </span>
                    <span
                        className={cn(
                            'text-[11px] text-muted-foreground',
                            day.is_today && 'font-semibold text-foreground',
                        )}
                    >
                        {day.short}
                    </span>
                </li>
            ))}
        </ol>
    );
}
