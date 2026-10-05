import { Link } from '@inertiajs/react';
import { ChevronRight, Flame } from 'lucide-react';
import { ReadingRow } from '@/components/lesson/reading-plan';
import { WeekBar } from '@/components/progress/week-bar';
import { cn } from '@/lib/utils';
import type { LessonReading, StudyWeek, StudyWeekDay } from '@/types';

/**
 * "Sua semana" no Início: os 7 dias, quantas leituras já foram, a sequência
 * e a leitura de hoje, com o botão que abre o leitor (onde ficam a
 * apresentação, o texto e o "Marcar como lido").
 */
export function WeekSummary({
    days,
    progress,
    streak,
    href,
    today,
    onRead,
}: {
    days: StudyWeekDay[];
    progress: NonNullable<StudyWeek['progress']>;
    streak: StudyWeek['streak'];
    href: string;
    /** A leitura de hoje e o dia dela, quando hoje tem leitura. */
    today: { day: StudyWeekDay; reading: LessonReading } | null;
    onRead: () => void;
}) {
    const active = streak.current > 0;

    return (
        <section
            aria-label="Sua semana"
            className="mt-4 rounded-2xl border bg-card p-4"
        >
            <div className="mb-3 flex items-center justify-between gap-3">
                <h2 className="font-semibold">Sua semana</h2>
                <Link
                    href={href}
                    className="inline-flex min-h-9 items-center gap-0.5 rounded-lg text-sm font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    Todas as leituras <ChevronRight className="size-4" />
                </Link>
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
            {today && (
                <ReadingRow
                    className="mt-4"
                    badge={today.day.short}
                    label="Leitura de hoje"
                    reference={today.reading.reference}
                    isToday
                    done={today.day.done}
                    onRead={onRead}
                />
            )}
        </section>
    );
}
