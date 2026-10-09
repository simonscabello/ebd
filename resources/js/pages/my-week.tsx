import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, CalendarDays, Hourglass } from 'lucide-react';
import { ReadingRow, ReadToggle } from '@/components/lesson/reading-plan';
import { ReadingSheet } from '@/components/lesson/reading-sheet';
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
    const [open, setOpen] = useState<{
        weekday: number;
        reading: LessonReading;
    } | null>(null);

    // Uma linha por leitura do plano. Sem plano, cada dia de segunda a sábado
    // é para reler o texto base.
    const withReadings = days.filter((d) => d.readings.length > 0);
    const rows =
        withReadings.length > 0
            ? withReadings.flatMap((day) =>
                  day.readings.map((reading) => ({ day, reading })),
              )
            : days
                  .filter((d) => d.weekday <= 6)
                  .map((day) => ({
                      day,
                      reading: baseTextReading(day, lesson.bible_reference),
                  }));

    const openDay = open
        ? (days.find((d) => d.weekday === open.weekday) ?? null)
        : null;

    return (
        <div className="space-y-6">
            <ReadingSheet
                reading={open?.reading ?? null}
                open={open !== null}
                onOpenChange={(value) => !value && setOpen(null)}
                footer={
                    openDay ? (
                        <ReadToggle
                            done={openDay.done}
                            pending={checkin.isPending(openDay.weekday)}
                            highlight
                            onClick={() =>
                                checkin.toggle(
                                    openDay.weekday,
                                    openDay.done,
                                    open?.reading.id && open.reading.id > 0
                                        ? open.reading.id
                                        : null,
                                )
                            }
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
                {rows.map(({ day, reading }) => (
                    <li key={`${day.date}-${reading.id}`}>
                        <ReadingRow
                            badge={day.short}
                            label={
                                day.is_today ? `Hoje · ${day.label}` : day.label
                            }
                            reference={reading.reference}
                            isToday={day.is_today}
                            done={day.done}
                            onRead={() =>
                                setOpen({ weekday: day.weekday, reading })
                            }
                        />
                    </li>
                ))}
            </ul>
        </div>
    );
}

/**
 * Sem plano de leitura, o dia é para reler o texto base: o leitor abre com a
 * orientação e o "Marcar como lido".
 */
function baseTextReading(
    day: StudyWeekDay,
    reference: string | null,
): LessonReading {
    return {
        id: -day.weekday,
        weekday: day.weekday,
        weekday_label: day.label,
        weekday_short: day.short,
        is_today: day.is_today,
        reference: reference ?? 'Texto base da lição',
        passage: null,
        notes: 'Releia o texto base da lição.',
        position: day.weekday,
    };
}

function countdown(days: number): string {
    if (days <= 0) return 'hoje é dia de EBD';
    if (days === 1) return 'amanhã é dia de EBD';

    return `faltam ${days} dias para domingo`;
}
