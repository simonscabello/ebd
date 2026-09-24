import { Head, Link } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    CheckCircle2,
    Circle,
    Hourglass,
    Lightbulb,
    ListChecks,
} from 'lucide-react';
import { InstallAppBanner } from '@/components/install-app-banner';
import { BlockAccordion, BlockCards } from '@/components/lesson/lesson-blocks';
import { LessonHero } from '@/components/lesson/lesson-hero';
import { PassageText } from '@/components/lesson/passage-text';
import { ReadToggle } from '@/components/lesson/reading-plan';
import { EmptyState, Page, Section } from '@/components/page';
import type { Streak } from '@/components/progress/streak-flame';
import { StreakFlame } from '@/components/progress/streak-flame';
import type { WeekDay } from '@/components/progress/week-bar';
import { WeekBar } from '@/components/progress/week-bar';
import { Button } from '@/components/ui/button';
import { useReadingCheckin } from '@/hooks/use-reading-checkin';
import { cn } from '@/lib/utils';
import { myProgress, myWeek } from '@/routes';
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
                <InstallAppBanner />
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
                                aria-current={
                                    item.id === classroom.id
                                        ? 'true'
                                        : undefined
                                }
                                className={cn(
                                    'inline-flex min-h-9 shrink-0 items-center rounded-full border px-4 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
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
    const checkin = useReadingCheckin(lesson.slug);
    const otherUnlocked = (week.unlockedBlocks ?? []).filter(
        (b) => !(week.todayBlocks ?? []).some((t) => t.id === b.id),
    );

    // Dias com leitura no plano; sem plano, a semana toda (segunda a sábado) é
    // para reler o texto base.
    const withReadings = days.filter((d) => d.readings.length > 0);
    const readingDays =
        withReadings.length > 0
            ? withReadings
            : days.filter((d) => d.weekday <= 6);

    const toggle = (day: Day) =>
        checkin.toggle(day.weekday, day.done, day.readings[0]?.id ?? null);

    return (
        <div className="space-y-8">
            <LessonHero
                lesson={lesson}
                eyebrow={
                    week.meeting
                        ? `${week.meeting.date_label}${week.meeting.total > 1 ? ` · encontro ${week.meeting.index} de ${week.meeting.total}` : ''}`
                        : 'Última lição'
                }
            />

            <section className="space-y-3">
                <WeekBar days={days} />
                {week.progress && (
                    <p className="text-center text-sm text-muted-foreground">
                        {week.progress.days_done} de {week.progress.days_total}{' '}
                        leituras da semana
                    </p>
                )}
            </section>

            <Section
                title="Leituras da semana"
                icon={<CalendarDays />}
                description="Marque quando ler. Pode adiantar ou pôr em dia quando quiser."
            >
                <ul className="space-y-2.5">
                    {readingDays.map((day) => (
                        <DayReading
                            key={day.date}
                            day={day}
                            fallback={lesson.bible_reference}
                            pending={checkin.isPending(day.weekday)}
                            onToggle={() => toggle(day)}
                        />
                    ))}
                </ul>
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
                                        <CheckCircle2 className="size-5 shrink-0 text-success" />
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

function DayReading({
    day,
    fallback,
    pending,
    onToggle,
}: {
    day: Day;
    fallback: string | null;
    pending: boolean;
    onToggle: () => void;
}) {
    return (
        <li
            className={cn(
                'grid grid-cols-[3rem_1fr] items-center gap-x-3.5 gap-y-3 rounded-2xl border bg-card p-3.5 transition-colors sm:grid-cols-[3rem_1fr_auto]',
                day.is_today &&
                    !day.done &&
                    'border-primary/50 bg-accent/60 ring-1 ring-primary/20',
                day.done && 'border-success/40 bg-success-soft',
            )}
        >
            <span
                className={cn(
                    'flex size-12 shrink-0 items-center justify-center rounded-xl bg-muted text-xs font-semibold text-muted-foreground uppercase',
                    day.is_today && 'bg-primary text-primary-foreground',
                    day.done && 'bg-success text-white',
                )}
            >
                {day.done ? <Check className="size-5" /> : day.short}
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-xs font-medium text-muted-foreground">
                    {day.is_today ? `Hoje · ${day.label}` : day.label}
                </p>
                {day.readings.length > 0 ? (
                    day.readings.map((reading) => (
                        <div key={reading.id}>
                            <p className="font-serif text-lg font-semibold">
                                {reading.reference}
                            </p>
                            {reading.notes && (
                                <p className="text-sm text-pretty text-muted-foreground">
                                    {reading.notes}
                                </p>
                            )}
                            <PassageText
                                passage={reading.passage}
                                defaultOpen={day.is_today && !day.done}
                                className="mt-1.5"
                            />
                        </div>
                    ))
                ) : (
                    <p className="font-serif text-lg font-semibold">
                        {fallback
                            ? `Releia ${fallback}`
                            : 'Releia o texto base'}
                    </p>
                )}
            </div>
            <ReadToggle
                done={day.done}
                pending={pending}
                highlight={day.is_today}
                onClick={onToggle}
            />
        </li>
    );
}

function countdown(days: number): string {
    if (days <= 0) return 'Hoje é dia de EBD!';
    if (days === 1) return 'Amanhã é dia de EBD.';

    return `Faltam ${days} dias para domingo.`;
}
