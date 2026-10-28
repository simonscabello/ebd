import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    CalendarOff,
    Check,
    ChevronRight,
    Flame,
    Hourglass,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { InstallAppBanner } from '@/components/install-app-banner';
import { LessonHero } from '@/components/lesson/lesson-hero';
import { ReadingRow, ReadToggle } from '@/components/lesson/reading-plan';
import { ReadingSheet } from '@/components/lesson/reading-sheet';
import { RemindersBanner } from '@/components/reminders-banner';
import { EmptyState, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { DailyBlocks } from '@/components/week/daily-blocks';
import { WeekSummary } from '@/components/week/week-summary';
import { useReadingCheckin } from '@/hooks/use-reading-checkin';
import { cn, plural } from '@/lib/utils';
import { home, library, login, myProgress, myWeek } from '@/routes';
import { show } from '@/routes/lessons';
import type {
    ClassMeeting,
    Classroom,
    Lesson,
    LessonReading,
    StudyWeek,
} from '@/types';

/** A série da lição atual: posição, quantas já foram e as próximas. */
type LessonSeries = {
    id: number;
    title: string;
    /** Posição na ordem da série (a barrinha). */
    index: number;
    total: number;
    /** O número da revista (sem número, a posição) e o maior número da série. */
    number: number;
    of: number;
    studied: number;
    /** Sem título: a pessoa ainda não pode ver (rascunho). */
    upcoming: {
        index: number;
        title: string | null;
        slug: string | null;
        bible_reference: string | null;
    }[];
};

type Props = {
    greeting: string;
    today: { date: string; weekday: number; label: string };
    classrooms: Classroom[];
    classroom: Classroom | null;
    isMember: boolean;
    isStudent: boolean;
    nextLesson: Lesson | null;
    /** Semana de estudo (só para quem é da classe e tem lição). */
    week: StudyWeek | null;
    meeting: ClassMeeting | null;
    meetingIndex: number;
    meetingTotal: number;
    preparing: boolean;
    cancelledBefore: ClassMeeting[];
    series: LessonSeries | null;
    /** Só sem lição atual: as últimas estudadas. */
    recentLessons: Lesson[];
};

export default function Home({
    greeting,
    classrooms,
    classroom,
    isMember,
    nextLesson,
    week,
    meeting,
    meetingIndex,
    preparing,
    cancelledBefore,
    series,
    recentLessons,
}: Props) {
    const { auth } = usePage().props;
    const name = auth.user?.first_name;
    // Duas colunas no computador só quando há semana para pôr ao lado.
    const wide = nextLesson !== null && (week?.days ?? []).length > 0;

    return (
        <>
            <Head title="Início" />

            <Page className={cn(wide && 'lg:max-w-5xl')}>
                <InstallAppBanner />
                {isMember && <RemindersBanner />}
                <header className="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h1 className="font-serif text-3xl font-semibold tracking-tight md:text-4xl">
                            {greeting}
                            {name ? `, ${name}` : ''}.
                        </h1>
                        <p className="mt-2 text-lg text-muted-foreground">
                            {headline(
                                meeting,
                                classroom?.program_short_name ?? 'EBD',
                            )}
                        </p>
                    </div>
                    {week && (
                        <StreakChip
                            streak={week.streak}
                            className={cn(wide && 'lg:hidden')}
                        />
                    )}
                </header>

                {classrooms.length > 1 && classroom && (
                    <nav
                        className="-mx-4 mb-6 flex gap-2 overflow-x-auto px-4 pb-1"
                        aria-label="Escolher classe"
                    >
                        {classrooms.map((item) => (
                            <Link
                                key={item.id}
                                href={home({ query: { classe: item.slug } })}
                                preserveScroll
                                aria-current={
                                    item.id === classroom.id
                                        ? 'true'
                                        : undefined
                                }
                                className={cn(
                                    'shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium transition-colors',
                                    item.id === classroom.id
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'bg-card text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {item.name}
                            </Link>
                        ))}
                    </nav>
                )}

                {cancelledBefore.map((cancelled) => (
                    <p
                        key={cancelled.id}
                        className="mb-3 flex items-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200"
                    >
                        <CalendarOff className="size-4 shrink-0" />
                        <span className="first-letter:uppercase">
                            {cancelled.date_short}: não teremos{' '}
                            {classroom?.program_short_name ?? 'EBD'}
                            {cancelled.title ? ` (${cancelled.title})` : ''}.
                        </span>
                    </p>
                ))}

                {nextLesson ? (
                    <CurrentLesson
                        lesson={nextLesson}
                        series={series}
                        week={week}
                        classroom={classroom}
                        meeting={meeting}
                        meetingIndex={meetingIndex}
                        wide={wide}
                    />
                ) : preparing && meeting ? (
                    <EmptyState
                        icon={<Hourglass />}
                        title="A próxima lição está sendo preparada"
                    >
                        <span className="first-letter:uppercase">
                            {meeting.date_label}
                        </span>
                        . Assim que for publicada, ela aparece aqui.
                    </EmptyState>
                ) : (
                    <EmptyState
                        icon={<CalendarDays />}
                        title="A próxima aula ainda não foi publicada"
                    >
                        Enquanto isso, que tal revisar uma aula anterior?
                    </EmptyState>
                )}

                {recentLessons.length > 0 && (
                    <section className="mt-12">
                        <div className="mb-3 flex items-baseline justify-between">
                            <h2 className="text-lg font-semibold tracking-tight">
                                Aulas anteriores
                            </h2>
                            <Link
                                href={library({
                                    query: classroom
                                        ? { classe: classroom.id }
                                        : {},
                                })}
                                className="text-sm font-medium text-primary"
                            >
                                Ver biblioteca
                            </Link>
                        </div>
                        <ul className="divide-y rounded-2xl border bg-card">
                            {recentLessons.map((lesson) => (
                                <li key={lesson.id}>
                                    <Link
                                        href={show(lesson.slug)}
                                        className="flex items-center justify-between gap-4 px-4 py-3.5 hover:bg-muted/50"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate font-medium">
                                                {lesson.display_title}
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                {lesson.date_short}
                                                {lesson.bible_reference &&
                                                    ` · ${lesson.bible_reference}`}
                                            </span>
                                        </span>
                                        <ArrowRight className="size-4 shrink-0 text-muted-foreground" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {!auth.user && (
                    <p className="mt-10 rounded-2xl bg-muted/70 p-4 text-sm text-muted-foreground">
                        Faz parte de uma classe? Peça ao seu professor o seu
                        link de acesso: com ele você marca as leituras, faz
                        anotações e acompanha seu progresso. Já tem senha?{' '}
                        <Link
                            href={login()}
                            className="font-medium text-primary"
                        >
                            Entre na sua conta
                        </Link>
                        .
                    </p>
                )}

                {auth.user && classroom && !isMember && !auth.user.is_admin && (
                    <p className="mt-10 rounded-2xl bg-muted/70 p-4 text-sm text-muted-foreground">
                        Você ainda não está vinculado(a) à classe{' '}
                        {classroom.name}. Peça ao seu professor para adicionar
                        você.
                    </p>
                )}
            </Page>
        </>
    );
}

function headline(meeting: ClassMeeting | null, program = 'EBD'): string {
    if (!meeting || meeting.days_until < 0) {
        return 'Que bom ter você por aqui.';
    }

    if (meeting.days_until === 0) {
        return `Hoje é dia de ${program}!`;
    }

    if (meeting.days_until === 1) {
        return `Amanhã tem ${program}. Vamos nos preparar?`;
    }

    // "nossa próxima EBD" pede artigo: os outros programas falam do encontro.
    return program === 'EBD'
        ? `Faltam ${meeting.days_until} dias para a nossa próxima EBD.`
        : `Faltam ${meeting.days_until} dias para o próximo encontro.`;
}

/** Quando a classe estuda a lição: previsão, não regra. */
function when(meeting: ClassMeeting | null, index: number): string | null {
    if (!meeting) {
        return null;
    }

    if (index > 1) {
        return `${index}º encontro desta lição: ${meeting.days_until === 0 ? 'hoje' : meeting.date_label}`;
    }

    return meeting.days_until === 0
        ? 'Aula de hoje'
        : `Previsão: ${meeting.date_label}`;
}

/**
 * A lição atual no Início: o destaque com as ações, a semana de leitura e a
 * série. No computador, a semana vai para uma coluna ao lado, com cada dia
 * para marcar.
 */
function CurrentLesson({
    lesson,
    series,
    week,
    classroom,
    meeting,
    meetingIndex,
    wide,
}: {
    lesson: Lesson;
    series: LessonSeries | null;
    week: StudyWeek | null;
    classroom: Classroom | null;
    meeting: ClassMeeting | null;
    meetingIndex: number;
    wide: boolean;
}) {
    const checkin = useReadingCheckin(lesson.slug);
    // O dia aberto no leitor; o dia em si vem sempre das props (marcar recarrega).
    const [openWeekday, setOpenWeekday] = useState<number | null>(null);
    const days = week?.days ?? [];
    const lessonUrl = show.url(lesson.slug);
    const until = week?.meeting?.day ?? 'a aula';
    const blocks = week?.todayBlocks ?? [];
    const todayDay = days.find((d) => d.is_today);
    const today =
        todayDay && todayDay.readings[0]
            ? { day: todayDay, reading: todayDay.readings[0] }
            : null;
    const openDay = days.find((d) => d.weekday === openWeekday);
    const openReading = openDay?.readings[0] ?? null;
    const guestReading = week
        ? null
        : ((lesson.readings ?? []).find((r) => r.is_today) ?? null);

    const summary = week?.progress && days.length > 0 && (
        <WeekSummary
            days={days}
            progress={week.progress}
            until={until}
            href={myWeek.url({
                query: classroom ? { classe: classroom.slug } : {},
            })}
            today={today}
            onRead={() => setOpenWeekday(today?.day.weekday ?? null)}
        />
    );

    return (
        <>
            {week && (
                <ReadingSheet
                    reading={openReading}
                    open={openReading !== null}
                    onOpenChange={(open) => !open && setOpenWeekday(null)}
                    footer={
                        openDay &&
                        openReading && (
                            <ReadToggle
                                done={openDay.done}
                                pending={checkin.isPending(openDay.weekday)}
                                highlight
                                onClick={() =>
                                    checkin.toggle(
                                        openDay.weekday,
                                        openDay.done,
                                        openReading.id,
                                    )
                                }
                                className="w-full justify-center"
                            />
                        )
                    }
                />
            )}

            <div
                className={cn(
                    wide &&
                        'lg:grid lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start lg:gap-6',
                )}
            >
                <div className="space-y-4">
                    <LessonHero
                        lesson={lesson}
                        series={series}
                        when={when(meeting, meetingIndex)}
                        continues={meetingIndex > 1}
                    >
                        <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                            <Button asChild size="lg" className="sm:flex-none">
                                <Link href={lessonUrl}>Estudar a lição</Link>
                            </Button>
                            <div
                                className={cn(
                                    'grid gap-2 sm:flex',
                                    week ? 'grid-cols-2' : 'grid-cols-1',
                                )}
                            >
                                <HeroLink href={`${lessonUrl}#materiais`}>
                                    Materiais
                                </HeroLink>
                                {week && (
                                    <HeroLink href={`${lessonUrl}#anotacoes`}>
                                        Anotações
                                    </HeroLink>
                                )}
                            </div>
                        </div>
                    </LessonHero>

                    <div className={cn('space-y-4', wide && 'lg:hidden')}>
                        {summary}
                        {guestReading && (
                            <GuestTodayReading reading={guestReading} />
                        )}
                    </div>

                    {series && (
                        <SeriesTrail
                            series={series}
                            lesson={lesson}
                            className="pt-4"
                        />
                    )}

                    {/* O conteúdo do dia fica na coluna principal: na lateral, ela ficava comprida demais. */}
                    {blocks.length > 0 && (
                        <DailyBlocks blocks={blocks} className="pt-4" />
                    )}
                </div>

                {wide && week && (
                    <aside className="hidden space-y-4 lg:block">
                        <WeekChecklist
                            week={week}
                            until={until}
                            checkin={checkin}
                            onRead={setOpenWeekday}
                        />
                        <Link
                            href={myProgress.url()}
                            className="flex items-center gap-3 rounded-2xl border bg-card p-4 transition-colors hover:bg-muted/50"
                        >
                            <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-accent text-accent-foreground">
                                <Flame className="size-5" />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block text-lg font-semibold">
                                    {week.streak.current > 0
                                        ? plural(
                                              week.streak.current,
                                              'dia seguido',
                                              'dias seguidos',
                                          )
                                        : 'Comece sua sequência hoje'}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    Recorde:{' '}
                                    {plural(week.streak.best, 'dia', 'dias')} ·
                                    ver selos
                                </span>
                            </span>
                            <ChevronRight className="size-4 text-muted-foreground" />
                        </Link>
                    </aside>
                )}
            </div>
        </>
    );
}

function HeroLink({ href, children }: { href: string; children: ReactNode }) {
    return (
        <Link
            href={href}
            className="inline-flex h-12 items-center justify-center rounded-xl border border-primary/20 bg-card/70 px-2 text-[13px] font-medium whitespace-nowrap transition-colors hover:bg-card focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:px-4 sm:text-sm"
        >
            {children}
        </Link>
    );
}

/** A sequência de leitura no topo; abre Meu progresso. */
function StreakChip({
    streak,
    className,
}: {
    streak: StudyWeek['streak'];
    className?: string;
}) {
    return (
        <Link
            href={myProgress.url()}
            aria-label={`Meu progresso: ${plural(streak.current, 'dia seguido', 'dias seguidos')} de leitura`}
            className={cn(
                'inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full bg-accent px-3 text-sm font-semibold text-accent-foreground',
                className,
            )}
        >
            <Flame className="size-4" />
            {streak.current > 0
                ? plural(streak.current, 'dia', 'dias')
                : 'Progresso'}
        </Link>
    );
}

/**
 * "Nesta série": as estudadas numa linha (abrem na biblioteca), a lição atual
 * e as próximas na ordem, sem data. A próxima ainda em rascunho aparece como
 * "em preparação".
 */
function SeriesTrail({
    series,
    lesson,
    className,
}: {
    series: LessonSeries;
    lesson: Lesson;
    className?: string;
}) {
    if (series.studied === 0 && series.upcoming.length === 0) {
        return null;
    }

    const seriesHref = library({ query: { serie: series.id } });
    const row =
        'flex min-h-13 items-center gap-3 px-4 py-2 transition-colors hover:bg-muted/50';

    return (
        <section aria-labelledby="nesta-serie" className={className}>
            <div className="mb-2 flex items-baseline justify-between">
                <h2
                    id="nesta-serie"
                    className="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Nesta série
                </h2>
                <Link
                    href={seriesHref}
                    className="text-sm font-medium text-primary"
                >
                    Ver todas
                </Link>
            </div>
            <ul className="divide-y overflow-hidden rounded-2xl border bg-card">
                {series.studied > 0 && (
                    <li>
                        <Link href={seriesHref} className={row}>
                            <Position className="bg-success-soft text-success-foreground">
                                <Check className="size-3.5" />
                            </Position>
                            <span className="flex-1">
                                {plural(
                                    series.studied,
                                    'lição estudada',
                                    'lições estudadas',
                                )}
                            </span>
                            <span className="text-sm text-muted-foreground">
                                Rever
                            </span>
                        </Link>
                    </li>
                )}
                <li>
                    <Link
                        href={lesson.url}
                        aria-current="true"
                        className={cn(row, 'bg-accent/50')}
                    >
                        <Position className="bg-primary text-primary-foreground">
                            {series.number}
                        </Position>
                        <span className="min-w-0 flex-1 truncate font-semibold">
                            {lesson.title}
                        </span>
                        <span className="rounded-md bg-success-soft px-2 py-0.5 text-xs font-medium text-success-foreground">
                            Agora
                        </span>
                    </Link>
                </li>
                {series.upcoming.map((item) => (
                    <li key={item.index}>
                        {item.slug ? (
                            <Link href={show(item.slug)} className={row}>
                                <Position>{item.index}</Position>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate">
                                        {item.title}
                                    </span>
                                    {item.bible_reference && (
                                        <span className="block text-xs text-muted-foreground">
                                            {item.bible_reference}
                                        </span>
                                    )}
                                </span>
                            </Link>
                        ) : (
                            <div className={cn(row, 'hover:bg-transparent')}>
                                <Position>{item.index}</Position>
                                <span className="flex-1 text-muted-foreground">
                                    Em preparação
                                </span>
                            </div>
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
}

function Position({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'flex size-7 shrink-0 items-center justify-center rounded-lg bg-muted text-xs font-semibold text-muted-foreground',
                className,
            )}
        >
            {children}
        </span>
    );
}

/**
 * As leituras até a aula, no computador: cada dia para marcar ali mesmo, e a
 * referência abre o leitor.
 */
function WeekChecklist({
    week,
    until,
    checkin,
    onRead,
}: {
    week: StudyWeek;
    until: string;
    checkin: ReturnType<typeof useReadingCheckin>;
    onRead: (weekday: number) => void;
}) {
    const days = (week.days ?? []).filter(
        (day) => !day.is_meeting_day && day.readings[0],
    );

    return (
        <section
            aria-labelledby="ate-a-aula"
            className="overflow-hidden rounded-2xl border bg-card"
        >
            <div className="flex items-baseline justify-between px-4 py-3">
                <h2
                    id="ate-a-aula"
                    className="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Até {until}
                </h2>
                {week.progress && (
                    <span className="text-xs text-muted-foreground">
                        {week.progress.days_done} de {week.progress.days_total}{' '}
                        lidas
                    </span>
                )}
            </div>
            <ul>
                {days.map((day) => {
                    const reading = day.readings[0];

                    return (
                        <li
                            key={day.date}
                            className={cn(
                                'flex min-h-12 items-center gap-1 border-t pr-4 pl-2',
                                day.is_today && 'bg-accent/50',
                            )}
                        >
                            <button
                                type="button"
                                onClick={() =>
                                    checkin.toggle(
                                        day.weekday,
                                        day.done,
                                        reading.id,
                                    )
                                }
                                disabled={checkin.isPending(day.weekday)}
                                aria-pressed={day.done}
                                aria-label={`${reading.reference} lida`}
                                className="flex size-11 shrink-0 items-center justify-center rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-60"
                            >
                                <span
                                    className={cn(
                                        'flex size-6 items-center justify-center rounded-full border-2',
                                        day.done
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-muted-foreground/50',
                                    )}
                                >
                                    {day.done && <Check className="size-3.5" />}
                                </span>
                            </button>
                            <span
                                className={cn(
                                    'w-10 shrink-0 text-[11px] font-semibold uppercase',
                                    day.is_today
                                        ? 'text-accent-foreground'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {day.is_today ? 'Hoje' : day.short}
                            </span>
                            <button
                                type="button"
                                onClick={() => onRead(day.weekday)}
                                className={cn(
                                    'min-h-11 flex-1 rounded-md text-left text-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    day.done && 'text-muted-foreground',
                                    day.is_today && 'font-semibold',
                                )}
                            >
                                {reading.reference}
                            </button>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

/**
 * Leitura de hoje para quem não é da classe: só ler (marcar é para membros).
 */
function GuestTodayReading({ reading }: { reading: LessonReading }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <ReadingSheet
                reading={open ? reading : null}
                open={open}
                onOpenChange={setOpen}
            />
            <ReadingRow
                badge={reading.weekday_short ?? '•'}
                label="Leitura de hoje"
                reference={reading.reference}
                isToday
                onRead={() => setOpen(true)}
            />
        </>
    );
}
