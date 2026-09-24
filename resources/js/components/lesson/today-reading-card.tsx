import { Link } from '@inertiajs/react';
import { ArrowRight, BookOpen, Check } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { LessonReading } from '@/types';

/**
 * "Leitura de hoje" no Início: o dia da semana como selo, a referência, um
 * gostinho do texto e o convite para ler. Quando a pessoa já marcou, o card
 * muda para o verde de concluído.
 */
export function TodayReadingCard({
    reading,
    done = false,
    href,
}: {
    reading: LessonReading;
    done?: boolean;
    href: string;
}) {
    const snippet = reading.passage?.verses[0]?.text;

    return (
        <Link
            href={href}
            className={cn(
                'mt-4 block rounded-2xl border p-4 transition-colors',
                done
                    ? 'border-success/40 bg-success-soft hover:bg-success/10'
                    : 'border-highlight bg-highlight/40 hover:bg-highlight/60',
            )}
        >
            <div className="flex gap-3.5">
                <span
                    className={cn(
                        'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-xs font-semibold uppercase',
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
                    <p className="mt-2.5 inline-flex items-center gap-1 text-sm font-semibold text-primary">
                        {done ? 'Rever a leitura' : 'Ler agora'}
                        <ArrowRight className="size-4" />
                    </p>
                </div>
            </div>
        </Link>
    );
}
