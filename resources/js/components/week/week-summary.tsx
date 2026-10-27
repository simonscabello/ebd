import { Link } from '@inertiajs/react';
import { ChevronRight, CircleCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { LessonReading, StudyWeek, StudyWeekDay } from '@/types';

/**
 * "Sua semana" no Início, em pouco espaço: a leitura de hoje (o botão abre o
 * leitor, onde fica o "Marcar como lido") e quantas leituras já foram até a
 * aula. A semana inteira fica em Minha semana.
 */
export function WeekSummary({
    days,
    progress,
    until,
    href,
    today,
    onRead,
}: {
    days: StudyWeekDay[];
    progress: NonNullable<StudyWeek['progress']>;
    /** O dia da aula, minúsculo ("domingo"). */
    until: string;
    href: string;
    /** A leitura de hoje e o dia dela, quando hoje tem leitura. */
    today: { day: StudyWeekDay; reading: LessonReading } | null;
    onRead: () => void;
}) {
    return (
        <section
            aria-label="Sua semana"
            className="rounded-2xl border bg-card p-4"
        >
            {today && (
                <div className="mb-3 flex items-center gap-3 border-b pb-3">
                    <div className="min-w-0 flex-1">
                        <p className="text-sm text-muted-foreground">
                            Leitura de hoje · {today.day.label.toLowerCase()}
                        </p>
                        <p className="mt-0.5 font-serif text-lg font-semibold">
                            {today.reading.reference}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant={today.day.done ? 'outline' : 'default'}
                        onClick={onRead}
                        className="h-11 rounded-xl px-5"
                    >
                        {today.day.done && <CircleCheck />}
                        {today.day.done ? 'Lida' : 'Ler'}
                    </Button>
                </div>
            )}
            <Link
                href={href}
                className="flex min-h-9 items-center gap-3 rounded-lg text-sm text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <span className="flex gap-1.5" aria-hidden>
                    {days
                        .filter((day) => !day.is_meeting_day)
                        .map((day) => (
                            <span
                                key={day.date}
                                className={cn(
                                    'size-2.5 rounded-full border-[1.5px]',
                                    day.done
                                        ? 'border-primary bg-primary'
                                        : 'border-muted-foreground/50',
                                )}
                            />
                        ))}
                </span>
                <span className="flex-1">
                    {/* Dias, não leituras: o dia da aula não entra na conta. */}
                    {progress.days_done} de {progress.days_total} até {until}
                </span>
                <span className="inline-flex items-center font-medium text-primary">
                    Ver a semana <ChevronRight className="size-4" />
                </span>
            </Link>
        </section>
    );
}
