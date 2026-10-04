import { Link } from '@inertiajs/react';
import { ChevronRight, Flame } from 'lucide-react';
import { WeekBar } from '@/components/progress/week-bar';
import { cn } from '@/lib/utils';
import type { StudyWeek, StudyWeekDay } from '@/types';

/**
 * Resumo da semana no Início: os 7 dias, quantas leituras já foram e a
 * sequência. O card inteiro abre "Leituras da semana".
 */
export function WeekSummary({
    days,
    progress,
    streak,
    href,
}: {
    days: StudyWeekDay[];
    progress: NonNullable<StudyWeek['progress']>;
    streak: StudyWeek['streak'];
    href: string;
}) {
    const active = streak.current > 0;

    return (
        <Link
            href={href}
            className="mt-4 block rounded-2xl border bg-card p-4 transition-colors hover:border-primary/40 hover:bg-accent/30"
        >
            <div className="mb-3 flex items-center justify-between gap-3">
                <h2 className="font-semibold">Sua semana</h2>
                <span className="inline-flex items-center gap-0.5 text-sm font-medium text-primary">
                    Todas as leituras <ChevronRight className="size-4" />
                </span>
            </div>
            <WeekBar days={days} />
            <div className="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-sm text-muted-foreground">
                <span>
                    {progress.days_done} de {progress.days_total}{' '}
                    {progress.days_total === 1 ? 'leitura' : 'leituras'}
                </span>
                <span className="inline-flex items-center gap-1">
                    <Flame
                        className={cn(
                            'size-4',
                            active && 'text-orange-600 dark:text-orange-400',
                        )}
                    />
                    {active
                        ? `${streak.current} ${streak.current === 1 ? 'dia seguido' : 'dias seguidos'}`
                        : 'Comece sua sequência hoje'}
                </span>
            </div>
        </Link>
    );
}
