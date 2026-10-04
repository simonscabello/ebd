import { BookOpen, Check } from 'lucide-react';
import { ReadToggle } from '@/components/lesson/reading-plan';
import { verseCount } from '@/components/lesson/passage-text';
import { cn } from '@/lib/utils';
import type { LessonReading } from '@/types';

/**
 * Leitura de hoje no Início, para quem é da classe: ler o texto (abre o
 * leitor) e marcar como lida ali mesmo, sem trocar de tela.
 */
export function TodayReading({
    reading,
    done,
    pending,
    onToggle,
    onRead,
}: {
    reading: LessonReading;
    done: boolean;
    pending: boolean;
    onToggle: () => void;
    onRead: () => void;
}) {
    const snippet = reading.passage?.verses[0]?.text;
    const canRead = (reading.passage?.verses.length ?? 0) > 0;

    return (
        <section
            aria-label="Leitura de hoje"
            className={cn(
                'mt-4 rounded-2xl border p-4 transition-colors',
                done
                    ? 'border-success/40 bg-success-soft'
                    : 'border-highlight bg-highlight/40',
            )}
        >
            <div className="flex gap-3.5">
                <span
                    className={cn(
                        'flex size-12 shrink-0 items-center justify-center rounded-xl text-xs font-semibold uppercase',
                        done
                            ? 'bg-success text-white'
                            : 'bg-primary text-primary-foreground',
                    )}
                >
                    {done ? (
                        <Check className="size-5" />
                    ) : (
                        (reading.weekday_short ?? (
                            <BookOpen className="size-5" />
                        ))
                    )}
                </span>
                <div className="min-w-0 flex-1">
                    <p
                        className={cn(
                            'text-xs font-medium',
                            done
                                ? 'text-success-foreground'
                                : 'text-highlight-foreground',
                        )}
                    >
                        {done ? 'Leitura de hoje · lida' : 'Leitura de hoje'}
                    </p>
                    <p className="font-serif text-xl leading-tight font-semibold">
                        {reading.reference}
                    </p>
                    {reading.notes && (
                        <p className="mt-1 text-sm text-pretty text-muted-foreground">
                            {reading.notes}
                        </p>
                    )}
                    {snippet && !done && (
                        <p className="mt-2 line-clamp-2 font-serif leading-snug text-foreground/80">
                            “{snippet}”
                        </p>
                    )}
                </div>
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-2">
                {canRead && (
                    <button
                        type="button"
                        onClick={onRead}
                        className="inline-flex min-h-11 items-center gap-1.5 rounded-xl border bg-card px-3 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <BookOpen className="size-4" />
                        {done ? 'Reler' : 'Ler o texto'}
                        {reading.passage && (
                            <span className="text-muted-foreground">
                                · {verseCount(reading.passage)}
                            </span>
                        )}
                    </button>
                )}
                <ReadToggle
                    done={done}
                    pending={pending}
                    highlight={!done}
                    onClick={onToggle}
                />
            </div>
        </section>
    );
}
