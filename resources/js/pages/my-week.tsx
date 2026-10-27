import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import {
    BookOpen,
    CalendarDays,
    Check,
    ChevronRight,
    Hourglass,
} from 'lucide-react';
import { BlockIcon } from '@/components/lesson/block-icon';
import { ReadToggle } from '@/components/lesson/reading-plan';
import { ReadingSheet } from '@/components/lesson/reading-sheet';
import { EmptyState, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { DailyBlocks } from '@/components/week/daily-blocks';
import { useReadingCheckin } from '@/hooks/use-reading-checkin';
import { cn } from '@/lib/utils';
import { myWeek } from '@/routes';
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
 * Minha semana (aba do menu): a semana de estudo dia a dia até a aula. Cada
 * dia traz a leitura (para ler e marcar, adiantar ou pôr em dia) e o conteúdo
 * liberado nele. Os próximos dias só avisam quando o conteúdo chega; nada é
 * bloqueado (a lição mostra tudo).
 */
export default function WeekReadings({ classrooms, classroom, week }: Props) {
    return (
        <>
            <Head title="Minha semana" />
            <Page>
                <header className="mb-6">
                    <h1 className="font-serif text-3xl font-semibold tracking-tight">
                        Minha semana
                    </h1>
                    {week?.lesson && (
                        <p className="mt-1 text-muted-foreground">
                            {week.lesson.display_title}
                            {week.meeting &&
                                ` · ${countdown(
                                    week.meeting.days_until,
                                    week.meeting.day,
                                    classroom?.program_short_name ?? 'EBD',
                                )}`}
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
                        Assim que o professor publicar, as leituras e o conteúdo
                        de cada dia aparecem aqui.
                    </EmptyState>
                ) : (
                    <DayList week={week} lesson={week.lesson} />
                )}
            </Page>
        </>
    );
}

function DayList({
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

    // Sem plano de leitura, cada dia antes da aula é para reler o texto base.
    const hasPlan = days.some((d) => d.readings.length > 0);
    const readingsOf = (day: StudyWeekDay): LessonReading[] =>
        hasPlan
            ? day.readings
            : day.is_meeting_day
              ? []
              : [baseTextReading(day, lesson.bible_reference)];

    const openDay = open
        ? (days.find((d) => d.weekday === open.weekday) ?? null)
        : null;

    return (
        <div className="space-y-4">
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

            {week.progress && (
                <div className="flex items-center gap-3">
                    <div
                        className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                        aria-hidden
                    >
                        <div
                            className="h-full rounded-full bg-primary transition-[width]"
                            style={{
                                width: `${(week.progress.days_done / Math.max(week.progress.days_total, 1)) * 100}%`,
                            }}
                        />
                    </div>
                    <span className="text-sm font-semibold">
                        {week.progress.days_done} de {week.progress.days_total}
                    </span>
                </div>
            )}

            <ol
                aria-label="Leituras da semana"
                className="overflow-hidden rounded-2xl border bg-card"
            >
                {days
                    // O dia da aula só entra na lista se tiver leitura própria.
                    .filter(
                        (day) =>
                            !day.is_meeting_day || readingsOf(day).length > 0,
                    )
                    .map((day) => (
                        <li
                            key={day.date}
                            className="border-t first:border-t-0"
                        >
                            {readingsOf(day).map((reading, index) => (
                                <div
                                    key={reading.id}
                                    className={cn(
                                        'flex min-h-16 items-center gap-2 py-1.5 pr-3 pl-1',
                                        day.is_today && 'bg-accent/50',
                                    )}
                                >
                                    {index === 0 ? (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                checkin.toggle(
                                                    day.weekday,
                                                    day.done,
                                                    reading.id > 0
                                                        ? reading.id
                                                        : null,
                                                )
                                            }
                                            disabled={checkin.isPending(
                                                day.weekday,
                                            )}
                                            aria-pressed={day.done}
                                            aria-label={`${day.label}: ${reading.reference} lida`}
                                            className="flex size-11 shrink-0 items-center justify-center rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-60"
                                        >
                                            <span
                                                className={cn(
                                                    'flex size-6.5 items-center justify-center rounded-full border-2',
                                                    day.done
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : day.is_today
                                                          ? 'border-primary'
                                                          : 'border-muted-foreground/50',
                                                )}
                                            >
                                                {day.done && (
                                                    <Check className="size-3.5" />
                                                )}
                                            </span>
                                        </button>
                                    ) : (
                                        <span className="size-11 shrink-0" />
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p
                                            className={cn(
                                                'text-[11px] font-semibold tracking-wide uppercase',
                                                day.is_today
                                                    ? 'text-accent-foreground'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {day.is_today
                                                ? `Hoje · ${day.label}`
                                                : day.label}
                                        </p>
                                        <p
                                            className={cn(
                                                'font-serif text-[17px] leading-snug font-semibold',
                                                day.done &&
                                                    'text-muted-foreground',
                                            )}
                                        >
                                            {reading.reference}
                                        </p>
                                    </div>
                                    {day.is_today && !day.done ? (
                                        <Button
                                            type="button"
                                            onClick={() =>
                                                setOpen({
                                                    weekday: day.weekday,
                                                    reading,
                                                })
                                            }
                                            className="h-10 rounded-xl px-4"
                                        >
                                            Ler
                                        </Button>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setOpen({
                                                    weekday: day.weekday,
                                                    reading,
                                                })
                                            }
                                            aria-label={`Ler ${reading.reference}`}
                                            className="flex size-11 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            <ChevronRight className="size-5" />
                                        </button>
                                    )}
                                </div>
                            ))}
                            {day.blocks.length > 0 && !day.is_meeting_day && (
                                <div className="pr-3 pb-3 pl-13">
                                    {day.is_future ? (
                                        <ul className="space-y-1.5">
                                            {day.blocks.map((block) => (
                                                <li
                                                    key={block.id}
                                                    className="flex items-start gap-2 text-sm text-muted-foreground"
                                                >
                                                    <BlockIcon
                                                        kind={block.kind}
                                                        className="mt-0.5 size-4 shrink-0"
                                                    />
                                                    <span className="line-clamp-2 min-w-0 flex-1">
                                                        {block.kind_label}:{' '}
                                                        {block.display_title}
                                                    </span>
                                                    <span className="shrink-0 font-medium">
                                                        chega {arrives(day)}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <DailyBlocks
                                            blocks={day.blocks}
                                            label={
                                                day.is_today
                                                    ? 'Para hoje'
                                                    : day.label
                                            }
                                            className=""
                                        />
                                    )}
                                </div>
                            )}
                        </li>
                    ))}
            </ol>

            {days
                .filter((day) => day.is_meeting_day)
                .map((day) => (
                    <div key={day.date} className="space-y-3">
                        <Link
                            href={lesson.url}
                            className="flex items-center gap-3 rounded-2xl border border-primary/40 bg-accent/40 p-3 transition-colors hover:bg-accent/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                                <BookOpen className="size-5" />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block text-xs font-medium text-primary">
                                    {day.label} · aula
                                </span>
                                <span className="block truncate font-serif text-lg font-semibold">
                                    {lesson.display_title}
                                </span>
                            </span>
                            <ChevronRight className="size-5 shrink-0 text-muted-foreground" />
                        </Link>
                        {day.blocks.length > 0 && (
                            <DailyBlocks
                                blocks={day.blocks}
                                label={day.is_today ? 'Para hoje' : day.label}
                                className=""
                            />
                        )}
                    </div>
                ))}
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

function countdown(days: number, day: string, program = 'EBD'): string {
    if (days <= 0) return `hoje é dia de ${program}`;
    if (days === 1) return `amanhã é dia de ${program}`;

    return `faltam ${days} dias para ${day}`;
}

/** "na quarta", "no sábado": quando o conteúdo do dia chega. */
function arrives(day: StudyWeekDay): string {
    const name = day.label.split('-')[0].toLowerCase();

    return day.weekday >= 6 ? `no ${name}` : `na ${name}`;
}
