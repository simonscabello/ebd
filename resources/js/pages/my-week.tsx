import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, CalendarDays, Check, Hourglass } from 'lucide-react';
import { ReadToggle } from '@/components/lesson/reading-plan';
import {
    ReadingSheet,
    ReadTextButton,
} from '@/components/lesson/reading-sheet';
import { EmptyState, Page } from '@/components/page';
import { WeekBar } from '@/components/progress/week-bar';
import { useReadingCheckin } from '@/hooks/use-reading-checkin';
import { cn } from '@/lib/utils';
import { home, myWeek } from '@/routes';
import type {
    Classroom,
    LessonReading,
    StudyWeek,
    StudyWeekDay,
} from '@/types';

type Props = {
    classrooms: Classroom[];
    classroom: Classroom | null;
    week: StudyWeek | null;
};

/**
 * "Leituras da semana": só a lista dos dias, para ler e marcar (adiantar ou
 * pôr em dia). A leitura de hoje, o progresso e o conteúdo do dia ficam no
 * Início.
 */
export default function WeekReadings({ classrooms, classroom, week }: Props) {
    return (
        <>
            <Head title="Leituras da semana" />
            <Page>
                <Link
                    href={home({
                        query: classroom ? { classe: classroom.slug } : {},
                    })}
                    className="mb-4 inline-flex min-h-9 items-center gap-1.5 rounded-lg text-sm font-medium text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <ArrowLeft className="size-4" /> Início
                </Link>
                <header className="mb-6">
                    <h1 className="font-serif text-3xl font-semibold tracking-tight">
                        Leituras da semana
                    </h1>
                    {week?.lesson && (
                        <p className="mt-1 text-muted-foreground">
                            {week.lesson.display_title}
                            {week.meeting &&
                                ` · ${countdown(week.meeting.days_until)}`}
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
                    <EmptyState
                        icon={<Hourglass />}
                        title={
                            week.preparing
                                ? 'A próxima lição está sendo preparada'
                                : 'Nenhuma lição marcada ainda'
                        }
                    >
                        Assim que o professor publicar, as leituras da semana
                        aparecem aqui.
                    </EmptyState>
                ) : (
                    <ReadingList week={week} lesson={week.lesson} />
                )}
            </Page>
        </>
    );
}

function ReadingList({
    week,
    lesson,
}: {
    week: StudyWeek;
    lesson: NonNullable<StudyWeek['lesson']>;
}) {
    const days = week.days ?? [];
    const checkin = useReadingCheckin(lesson.slug);

    // Dias com leitura no plano; sem plano, a semana toda (segunda a sábado) é
    // para reler o texto base.
    const withReadings = days.filter((d) => d.readings.length > 0);
    const readingDays =
        withReadings.length > 0
            ? withReadings
            : days.filter((d) => d.weekday <= 6);

    const toggle = (day: StudyWeekDay) =>
        checkin.toggle(day.weekday, day.done, day.readings[0]?.id ?? null);

    const [reading, setReading] = useState<{
        day: StudyWeekDay;
        reading: LessonReading;
    } | null>(null);
    const openDay = reading
        ? (days.find((d) => d.weekday === reading.day.weekday) ?? reading.day)
        : null;

    return (
        <div className="space-y-6">
            <ReadingSheet
                reading={reading?.reading ?? null}
                open={reading !== null}
                onOpenChange={(open) => !open && setReading(null)}
                footer={
                    openDay ? (
                        <ReadToggle
                            done={openDay.done}
                            pending={checkin.isPending(openDay.weekday)}
                            highlight
                            onClick={() => toggle(openDay)}
                            className="w-full justify-center"
                        />
                    ) : undefined
                }
            />

            <section className="space-y-3">
                <WeekBar days={days} />
                {week.progress && (
                    <p className="text-center text-sm text-muted-foreground">
                        {week.progress.days_done} de {week.progress.days_total}{' '}
                        leituras da semana. Pode adiantar ou pôr em dia quando
                        quiser.
                    </p>
                )}
            </section>

            <ul className="space-y-2.5">
                {readingDays.map((day) => (
                    <DayReading
                        key={day.date}
                        day={day}
                        fallback={lesson.bible_reference}
                        pending={checkin.isPending(day.weekday)}
                        onToggle={() => toggle(day)}
                        onRead={(item) => setReading({ day, reading: item })}
                    />
                ))}
            </ul>
        </div>
    );
}

function DayReading({
    day,
    fallback,
    pending,
    onToggle,
    onRead,
}: {
    day: StudyWeekDay;
    fallback: string | null;
    pending: boolean;
    onToggle: () => void;
    onRead: (reading: LessonReading) => void;
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
                            <ReadTextButton
                                reading={reading}
                                onClick={() => onRead(reading)}
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
    if (days <= 0) return 'hoje é dia de EBD';
    if (days === 1) return 'amanhã é dia de EBD';

    return `faltam ${days} dias para domingo`;
}
