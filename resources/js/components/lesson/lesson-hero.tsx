import { Link } from '@inertiajs/react';
import { BookOpen, CalendarDays, Repeat } from 'lucide-react';
import type { ReactNode } from 'react';
import { LessonCover } from '@/components/lesson/lesson-cover';
import { cn } from '@/lib/utils';
import type { LessonStatus } from '@/types';

type HeroLesson = {
    url: string;
    /** Rascunho só chega aqui para professores: os alunos veem "em preparo". */
    status?: LessonStatus;
    title: string;
    bible_reference: string | null;
    cover_url?: string | null;
    cover_large_url?: string | null;
};

/**
 * Destaque do Início: a lição atual, em que ponto da série ela está e quando
 * a classe deve estudá-la (só uma previsão: a lição pode durar mais de um
 * encontro). As ações vêm de quem usa.
 *
 * Com capa, no tema claro a ilustração se funde ao cartão: o fundo papel dela
 * some no "multiply" e a borda se esfuma. No escuro o multiply escureceria a
 * cena, então a capa fica emoldurada ao lado do título.
 */
export function LessonHero({
    lesson,
    series,
    when,
    continues = false,
    children,
}: {
    lesson: HeroLesson;
    /** index/total: a barrinha (ordem da série); number/of: o número da revista. */
    series: {
        title: string;
        index: number;
        total: number;
        number: number;
        of: number;
    } | null;
    when: string | null;
    continues?: boolean;
    children: ReactNode;
}) {
    const cover = lesson.cover_large_url ?? lesson.cover_url ?? null;

    return (
        <section
            aria-label="Lição atual"
            className="relative overflow-hidden rounded-3xl border border-primary/25 bg-accent p-5 sm:p-7"
        >
            {cover && (
                <img
                    src={cover}
                    alt=""
                    aria-hidden
                    className="pointer-events-none absolute right-0 bottom-0 hidden h-full w-1/2 [mask-image:linear-gradient(to_right,transparent,black_35%)] object-cover object-bottom mix-blend-multiply sm:block dark:hidden"
                />
            )}

            <div className="relative">
                {series && (
                    <>
                        <div className="flex items-baseline justify-between gap-3 text-sm">
                            <span className="text-xs font-semibold tracking-[0.15em] text-primary uppercase">
                                {series.title}
                            </span>
                            <span className="shrink-0 text-accent-foreground">
                                {series.number === 0
                                    ? 'Introdução'
                                    : `Lição ${series.number} de ${series.of}`}
                            </span>
                        </div>
                        <div
                            role="img"
                            aria-label={`${series.index}ª de ${series.total} lições da série`}
                            className="mt-2.5 flex gap-1"
                        >
                            {Array.from({ length: series.total }, (_, i) => (
                                <span
                                    key={i}
                                    className={cn(
                                        'h-1.5 flex-1 rounded-full',
                                        i + 1 < series.index && 'bg-primary',
                                        i + 1 === series.index &&
                                            'bg-primary/50',
                                        i + 1 > series.index && 'bg-primary/15',
                                    )}
                                />
                            ))}
                        </div>
                    </>
                )}

                <div>
                    <div
                        className={cn(
                            'min-w-0',
                            cover && 'sm:max-w-[52%] dark:sm:max-w-none',
                        )}
                    >
                        {lesson.status === 'draft' && (
                            <p className="mt-4 inline-flex rounded-full bg-primary/15 px-2.5 py-0.5 text-xs font-semibold text-accent-foreground">
                                Rascunho: só professores veem
                            </p>
                        )}
                        {/* Tema escuro: a capa emoldurada, com o título em volta. */}
                        {lesson.cover_url && (
                            <LessonCover
                                src={lesson.cover_url}
                                className="float-right mt-5 ml-4 hidden w-24 sm:w-32 dark:block"
                            />
                        )}
                        <h2 className="mt-5 font-serif text-3xl leading-tight font-semibold text-balance sm:text-4xl">
                            <Link
                                href={lesson.url}
                                className="rounded-md underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                {lesson.title}
                            </Link>
                        </h2>
                        <div className="mt-4 space-y-2">
                            {lesson.bible_reference && (
                                <HeroFact icon={<BookOpen />}>
                                    <span className="font-medium text-foreground">
                                        {lesson.bible_reference}
                                    </span>
                                </HeroFact>
                            )}
                            {when && (
                                <HeroFact
                                    icon={
                                        continues ? (
                                            <Repeat />
                                        ) : (
                                            <CalendarDays />
                                        )
                                    }
                                >
                                    <span className="first-letter:uppercase">
                                        {when}
                                    </span>
                                </HeroFact>
                            )}
                        </div>
                        <div className="clear-both pt-6">{children}</div>
                    </div>
                </div>
            </div>

            {/* Celular, tema claro: a cena embaixo, esfumando para cima. */}
            {cover && (
                <img
                    src={cover}
                    alt=""
                    aria-hidden
                    className="pointer-events-none -mx-5 mt-2 -mb-5 aspect-[2/1] w-[calc(100%+2.5rem)] max-w-none [mask-image:linear-gradient(to_bottom,transparent,black_35%)] object-cover object-bottom mix-blend-multiply sm:hidden dark:hidden"
                />
            )}
        </section>
    );
}

function HeroFact({
    icon,
    children,
}: {
    icon: ReactNode;
    children: ReactNode;
}) {
    return (
        <p className="flex items-center gap-3 text-sm text-accent-foreground sm:text-base">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-background/70 text-primary [&_svg]:size-4">
                {icon}
            </span>
            {children}
        </p>
    );
}
