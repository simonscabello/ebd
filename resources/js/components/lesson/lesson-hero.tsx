import { Link } from '@inertiajs/react';
import { BookOpen, CalendarDays, Repeat } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { LessonStatus } from '@/types';

type HeroLesson = {
    url: string;
    /** Rascunho só chega aqui para professores: os alunos veem "em preparo". */
    status?: LessonStatus;
    title: string;
    bible_reference: string | null;
};

/**
 * Destaque do Início: a lição atual, em que ponto da série ela está e quando
 * a classe deve estudá-la (só uma previsão: a lição pode durar mais de um
 * encontro). As ações vêm de quem usa.
 */
export function LessonHero({
    lesson,
    series,
    when,
    continues = false,
    children,
}: {
    lesson: HeroLesson;
    series: { title: string; index: number; total: number } | null;
    when: string | null;
    continues?: boolean;
    children: ReactNode;
}) {
    return (
        <section
            aria-label="Lição atual"
            className="rounded-3xl border border-primary/25 bg-accent p-5 sm:p-6"
        >
            {series && (
                <>
                    <div className="flex items-baseline justify-between gap-3 text-sm">
                        <span className="font-semibold text-primary">
                            {series.title}
                        </span>
                        <span className="shrink-0 text-accent-foreground">
                            Lição {series.index} de {series.total}
                        </span>
                    </div>
                    <div
                        role="img"
                        aria-label={`Lição ${series.index} de ${series.total} da série`}
                        className="mt-2.5 flex gap-1"
                    >
                        {Array.from({ length: series.total }, (_, i) => (
                            <span
                                key={i}
                                className={cn(
                                    'h-1.5 flex-1 rounded-full',
                                    i + 1 < series.index && 'bg-primary',
                                    i + 1 === series.index && 'bg-primary/50',
                                    i + 1 > series.index && 'bg-primary/15',
                                )}
                            />
                        ))}
                    </div>
                </>
            )}
            {lesson.status === 'draft' && (
                <p className="mt-4 inline-flex rounded-full bg-primary/15 px-2.5 py-0.5 text-xs font-semibold text-accent-foreground">
                    Rascunho: só professores veem
                </p>
            )}
            <h2 className="mt-4 font-serif text-3xl leading-tight font-semibold text-balance">
                <Link
                    href={lesson.url}
                    className="rounded-md underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {lesson.title}
                </Link>
            </h2>
            {lesson.bible_reference && (
                <p className="mt-1.5 flex items-center gap-2 text-foreground/85">
                    <BookOpen className="size-4" /> {lesson.bible_reference}
                </p>
            )}
            {when && (
                <p className="mt-3 flex items-center gap-2 text-sm text-accent-foreground">
                    {continues ? (
                        <Repeat className="size-4 shrink-0" />
                    ) : (
                        <CalendarDays className="size-4 shrink-0" />
                    )}
                    <span className="first-letter:uppercase">{when}</span>
                </p>
            )}
            <div className="mt-5">{children}</div>
        </section>
    );
}
