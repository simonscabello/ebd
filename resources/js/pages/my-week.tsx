import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpen,
    CalendarDays,
    CheckCircle2,
    Circle,
    CircleCheck,
    Hourglass,
    KeyRound,
    Lightbulb,
    ListChecks,
    Undo2,
} from 'lucide-react';
import { BlockAccordion, BlockCards } from '@/components/lesson/lesson-blocks';
import { EmptyState, Page, Section } from '@/components/page';
import type { Streak } from '@/components/progress/streak-flame';
import { StreakFlame } from '@/components/progress/streak-flame';
import type { WeekDay } from '@/components/progress/week-bar';
import { WeekBar } from '@/components/progress/week-bar';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { myProgress, myWeek } from '@/routes';
import {
    destroy as undoCheckin,
    store as storeCheckin,
} from '@/routes/lessons/checkins';
import type { Classroom, LessonBlock, LessonReading } from '@/types';

type Day = WeekDay & {
    label: string;
    readings: LessonReading[];
    blocks_count: number;
};

type Week = {
    today: string;
    weekday: number;
    streak: Streak;
    meeting: {
        held_on: string;
        date_label: string;
        days_until: number;
        index: number;
        total: number;
    } | null;
    preparing: boolean;
    lesson: {
        id: number;
        slug: string;
        url: string;
        display_title: string;
        number: number | null;
        title: string;
        bible_reference: string | null;
        key_verse: string | null;
        general_readings: LessonReading[];
    } | null;
    days?: Day[];
    todayBlocks?: LessonBlock[];
    unlockedBlocks?: LessonBlock[];
    progress?: { days_done: number; days_total: number };
    checklist?: {
        key: string;
        label: string;
        done: boolean | null;
        hidden?: boolean;
    }[];
    review?: { answered: number; total: number };
};

type Props = {
    classrooms: Classroom[];
    classroom: Classroom | null;
    week: Week | null;
};

/**
 * "Minha semana": o roteiro de estudo do aluno até domingo.
 */
export default function MyWeek({ classrooms, classroom, week }: Props) {
    return (
        <>
            <Head title="Minha semana" />
            <Page>
                <header className="mb-6">
                    <p className="text-sm font-medium text-primary">
                        {classroom ? `Classe ${classroom.name}` : 'EBD'}
                    </p>
                    <h1 className="mt-1 font-serif text-3xl font-semibold tracking-tight">
                        Minha semana
                    </h1>
                    {week?.meeting && (
                        <p className="mt-1 text-muted-foreground">
                            {countdown(week.meeting.days_until)}
                        </p>
                    )}
                </header>

                {classrooms.length > 1 && classroom && (
                    <nav
                        className="-mx-4 mb-6 flex gap-2 overflow-x-auto px-4"
                        aria-label="Escolher classe"
                    >
                        {classrooms.map((item) => (
                            <Link
                                key={item.id}
                                href={myWeek({ query: { classe: item.slug } })}
                                className={cn(
                                    'shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium',
                                    item.id === classroom.id
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'bg-card text-muted-foreground',
                                )}
                            >
                                {item.name}
                            </Link>
                        ))}
                    </nav>
                )}

                {!classroom || !week ? (
                    <EmptyState
                        icon={<CalendarDays />}
                        title="Você ainda não está em uma classe"
                    >
                        Peça ao seu professor para adicionar você à classe.
                    </EmptyState>
                ) : !week.lesson ? (
                    <div className="space-y-6">
                        <EmptyState
                            icon={<Hourglass />}
                            title={
                                week.preparing
                                    ? 'A próxima lição está sendo preparada'
                                    : 'Nenhuma lição marcada ainda'
                            }
                        >
                            Assim que o professor publicar, sua semana de estudo
                            aparece aqui.
                        </EmptyState>
                        <StreakFlame streak={week.streak} />
                    </div>
                ) : (
                    <WeekContent week={week} lesson={week.lesson} />
                )}
            </Page>
        </>
    );
}

function WeekContent({
    week,
    lesson,
}: {
    week: Week;
    lesson: NonNullable<Week['lesson']>;
}) {
    const days = week.days ?? [];
    const today = days.find((d) => d.is_today);
    const yesterday = days.find((d) => d.weekday === week.weekday - 1);
    const visit = { preserveScroll: true, preserveState: true };
    const todayReadings = today?.readings ?? [];
    const otherUnlocked = (week.unlockedBlocks ?? []).filter(
        (b) => !(week.todayBlocks ?? []).some((t) => t.id === b.id),
    );

    const check = (readingId: number | null, isYesterday = false) =>
        router.post(
            storeCheckin.url(lesson.slug),
            { reading_id: readingId, yesterday: isYesterday },
            visit,
        );

    const undo = (date: string) =>
        router.delete(undoCheckin.url(lesson.slug), {
            data: { date },
            ...visit,
        });

    return (
        <div className="space-y-8">
            <Link
                href={lesson.url}
                className="block rounded-3xl bg-primary p-6 text-primary-foreground shadow-sm transition-opacity hover:opacity-95"
            >
                <p className="text-sm font-medium opacity-90 first-letter:uppercase">
                    {week.meeting
                        ? `${week.meeting.date_label}${week.meeting.total > 1 ? ` · encontro ${week.meeting.index} de ${week.meeting.total}` : ''}`
                        : 'Última lição'}
                </p>
                {lesson.number && (
                    <p className="mt-3 text-sm font-semibold tracking-wide uppercase opacity-80">
                        Lição {lesson.number}
                    </p>
                )}
                <h2 className="mt-1 font-serif text-3xl leading-tight font-semibold text-balance">
                    {lesson.title}
                </h2>
                {lesson.bible_reference && (
                    <p className="mt-2 flex items-center gap-2 opacity-95">
                        <BookOpen className="size-4" /> {lesson.bible_reference}
                    </p>
                )}
                {lesson.key_verse && (
                    <p className="mt-3 flex gap-2 font-serif text-pretty italic opacity-95">
                        <KeyRound className="mt-1 size-4 shrink-0" />
                        {lesson.key_verse}
                    </p>
                )}
                <p className="mt-4 inline-flex items-center gap-1 text-sm font-semibold">
                    Abrir a lição <ArrowRight className="size-4" />
                </p>
            </Link>

            <section className="space-y-3">
                <WeekBar days={days} />
                {week.progress && (
                    <p className="text-center text-sm text-muted-foreground">
                        {week.progress.days_done} de {week.progress.days_total}{' '}
                        leituras da semana
                    </p>
                )}
            </section>

            <Section title="Hoje" icon={<CalendarDays />}>
                <div className="space-y-3">
                    {todayReadings.length > 0 ? (
                        todayReadings.map((reading) => (
                            <TodayReading
                                key={reading.id}
                                reading={reading}
                                done={!!today?.done}
                                onCheck={() => check(reading.id)}
                                onUndo={() => today && undo(today.date)}
                            />
                        ))
                    ) : (
                        <TodayReading
                            reading={null}
                            fallback={lesson.bible_reference}
                            done={!!today?.done}
                            onCheck={() => check(null)}
                            onUndo={() => today && undo(today.date)}
                        />
                    )}

                    {yesterday && !yesterday.done && (
                        <button
                            type="button"
                            onClick={() => check(null, true)}
                            className="text-sm font-medium text-primary"
                        >
                            Leu ontem e esqueceu de marcar? Marcar ontem
                        </button>
                    )}
                </div>
            </Section>

            {(week.todayBlocks ?? []).length > 0 && (
                <Section
                    title="Para hoje"
                    icon={<Lightbulb />}
                    description="Um detalhe por dia para o texto ganhar vida."
                >
                    <BlockCards
                        blocks={week.todayBlocks ?? []}
                        tone="highlight"
                    />
                </Section>
            )}

            {otherUnlocked.length > 0 && (
                <Section title="Já liberado nesta semana" icon={<Lightbulb />}>
                    <BlockAccordion blocks={otherUnlocked} />
                </Section>
            )}

            {week.checklist && (
                <Section title="Prepare-se para domingo" icon={<ListChecks />}>
                    <ul className="divide-y rounded-2xl border bg-card">
                        {week.checklist
                            .filter((item) => !item.hidden)
                            .map((item) => (
                                <li
                                    key={item.key}
                                    className="flex items-center gap-3 px-4 py-3"
                                >
                                    {item.done ? (
                                        <CheckCircle2 className="size-5 shrink-0 text-emerald-600" />
                                    ) : (
                                        <Circle className="size-5 shrink-0 text-muted-foreground" />
                                    )}
                                    <span
                                        className={cn(
                                            item.done &&
                                                'text-muted-foreground line-through',
                                        )}
                                    >
                                        {item.label}
                                    </span>
                                    {item.key === 'review' && !item.done && (
                                        <Link
                                            href={`${lesson.url}#revisao`}
                                            className="ml-auto shrink-0 text-sm font-medium text-primary"
                                        >
                                            Revisar
                                        </Link>
                                    )}
                                    {item.key === 'note' && !item.done && (
                                        <Link
                                            href={`${lesson.url}#anotacoes`}
                                            className="ml-auto shrink-0 text-sm font-medium text-primary"
                                        >
                                            Anotar
                                        </Link>
                                    )}
                                </li>
                            ))}
                    </ul>
                </Section>
            )}

            <section className="space-y-3">
                <StreakFlame streak={week.streak} />
                <Button asChild variant="outline" className="w-full">
                    <Link href={myProgress()}>Ver meu progresso e selos</Link>
                </Button>
            </section>
        </div>
    );
}

function TodayReading({
    reading,
    fallback,
    done,
    onCheck,
    onUndo,
}: {
    reading: LessonReading | null;
    fallback?: string | null;
    done: boolean;
    onCheck: () => void;
    onUndo: () => void;
}) {
    return (
        <div
            className={cn(
                'rounded-2xl border p-4',
                done
                    ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/40'
                    : 'border-primary/40 bg-accent/50',
            )}
        >
            <p className="text-sm font-medium text-muted-foreground">
                {reading ? 'Leitura de hoje' : 'Hoje não há leitura marcada'}
            </p>
            <p className="font-serif text-2xl font-semibold">
                {reading?.reference ??
                    (fallback ? `Releia ${fallback}` : 'Releia o texto base')}
            </p>
            {reading?.notes && (
                <p className="mt-1 text-sm text-muted-foreground">
                    {reading.notes}
                </p>
            )}
            <div className="mt-3">
                {done ? (
                    <div className="flex items-center gap-3">
                        <span className="inline-flex items-center gap-1.5 font-medium text-emerald-700 dark:text-emerald-400">
                            <CircleCheck className="size-5" /> Lido hoje
                        </span>
                        <button
                            type="button"
                            onClick={onUndo}
                            className="inline-flex items-center gap-1 text-sm text-muted-foreground"
                        >
                            <Undo2 className="size-3.5" /> Desfazer
                        </button>
                    </div>
                ) : (
                    <Button
                        size="lg"
                        onClick={onCheck}
                        className="w-full sm:w-auto"
                    >
                        <CircleCheck /> Li hoje
                    </Button>
                )}
            </div>
        </div>
    );
}

function countdown(days: number): string {
    if (days <= 0) return 'Hoje é dia de EBD!';
    if (days === 1) return 'Amanhã é dia de EBD.';

    return `Faltam ${days} dias para domingo.`;
}
