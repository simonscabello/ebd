import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarCheck,
    CalendarClock,
    Cake,
    ChevronRight,
    FileText,
    Flag,
    MessageCircleQuestion,
    MessageSquareText,
    Pencil,
    Presentation,
    StickyNote,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { MeetingRow } from '@/components/admin/meetings/meeting-row';
import type { MeetingRowData } from '@/components/admin/meetings/meeting-row';
import { StatusBadge } from '@/components/admin/status-badge';
import { CopyWhatsAppButtons } from '@/components/copy-whatsapp-button';
import { FinishMeetingDialog } from '@/components/lesson/finish-meeting-dialog';
import { EmptyState, Page, PageHeader, Section } from '@/components/page';
import { Button } from '@/components/ui/button';
import { WhatsAppButton } from '@/components/whatsapp-button';
import { dateTile, dayMonth, longDate, relativeDay } from '@/lib/dates';
import { meetingWords } from '@/lib/meeting-words';
import { periodPhrase } from '@/lib/period';
import type { StatsPeriod } from '@/lib/period';
import { plural } from '@/lib/utils';
import { birthdayMessage, missYouMessage } from '@/lib/whatsapp';
import {
    edit,
    questions,
    report,
    show as classroomPage,
} from '@/routes/admin/classrooms';
import {
    index as meetingsIndex,
    show as meetingPage,
} from '@/routes/admin/classrooms/meetings';
import {
    index as studentsIndex,
    show as studentPage,
} from '@/routes/admin/classrooms/students';
import { edit as editLesson } from '@/routes/admin/lessons';
import { sunday } from '@/routes/lessons';
import type {
    Classroom,
    LessonStatus,
    MeetingStatus,
    SundaySummary,
} from '@/types';

type SundayRow = SundaySummary & {
    id: number;
    held_on: string;
    lesson: string | null;
    title: string | null;
};

type Overview = {
    today: string;
    week: {
        meeting: {
            id: number;
            held_on: string;
            status: MeetingStatus;
            status_label: string;
            title: string | null;
            has_attendance: boolean;
            is_today: boolean;
            notes: string | null;
            is_finished: boolean;
        };
        lesson: {
            id: number;
            slug: string;
            display_title: string;
            status: LessonStatus;
        } | null;
        meeting_index: number;
        meeting_total: number;
        cancelled_before: { held_on: string; title: string | null }[];
        can_take_attendance: boolean;
        summary: SundaySummary | null;
        weekly_message: string | null;
    } | null;
    last_sunday: (SundayRow & { notes: string | null }) | null;
    recent: SundayRow[];
    pending: {
        id: number;
        held_on: string;
        lesson: string | null;
        title: string | null;
    }[];
    attention: {
        id: number;
        name: string;
        phone: string | null;
        reasons: string[];
        last_present_on: string | null;
    }[];
    home_study: {
        lesson_title: string;
        readings_total: number;
        readers: number;
        expected: number;
        rate: number | null;
        avg_days: number;
    } | null;
    birthdays: {
        id: number;
        name: string;
        phone: string | null;
        day: number;
        is_today: boolean;
        turning: number;
    }[];
    stats: {
        students: number;
        frequency: {
            sundays: number;
            present: number;
            expected: number;
            visitors: number;
            rate: number | null;
        };
        period: StatsPeriod;
    };
    thresholds: {
        missed_meetings: number;
        inactive_days: number;
        new_student_days: number;
    };
};

type UpcomingLesson = {
    meeting_id: number;
    held_on: string;
    is_today: boolean;
    meetings_count: number;
    lesson: {
        id: number;
        slug: string;
        display_title: string;
        bible_reference: string | null;
        status: LessonStatus;
    };
    message: string | null;
};

/** Paginador do Laravel serializado direto (sem API Resource). */
type Upcoming = {
    data: UpcomingLesson[];
    current_page: number;
    last_page: number;
    total: number;
};

type Props = {
    classroom: Classroom;
    overview: Overview;
    upcoming: Upcoming;
    sundays: MeetingRowData[];
    canEdit: boolean;
};

/**
 * Página da classe: a linha do tempo. O próximo domingo em destaque, os
 * seguintes com o que falta em cada um, os que já foram e, ao lado, os alunos
 * que pedem atenção. Domingos, alunos, dúvidas e relatório completos abrem
 * a partir daqui.
 */
export default function ClassroomOverview({
    classroom,
    overview,
    upcoming,
    sundays,
    canEdit,
}: Props) {
    const { week, stats } = overview;
    const here = classroomPage.url(classroom.slug);
    const words = meetingWords(classroom);
    // Domingos sem lição: dois bastam para lembrar de planejar; o resto é ruído.
    let undefinedShown = 0;
    const later = sundays.filter(
        (row) =>
            row.id !== week?.meeting.id &&
            (row.lesson !== null ||
                row.status === 'cancelled' ||
                undefinedShown++ < 2),
    );
    const frequency = stats.frequency.rate;

    return (
        <>
            <Head title={classroom.name} />

            <Page width="wide">
                <PageHeader
                    title={classroom.name}
                    description={[
                        plural(stats.students, 'aluno', 'alunos'),
                        frequency !== null &&
                            `${frequency}% de frequência ${periodPhrase(stats.period)}`,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={meetingsIndex(classroom.slug)}>
                                    <CalendarClock /> Planejar {words.many}
                                </Link>
                            </Button>
                            {canEdit && (
                                <Button asChild variant="outline">
                                    <Link href={edit(classroom.slug)}>
                                        <Pencil /> Editar classe
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                {week ? (
                    <WeekCard classroom={classroom} week={week} here={here} />
                ) : (
                    <EmptyState
                        icon={<CalendarClock />}
                        title={`Nenhum ${words.one} planejado`}
                    >
                        <p>
                            Planeje os próximos {words.many} para a classe saber
                            o que estudar.
                        </p>
                        <Button asChild className="mt-4">
                            <Link href={meetingsIndex(classroom.slug)}>
                                Abrir {words.Many}
                            </Link>
                        </Button>
                    </EmptyState>
                )}

                <div className="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="min-w-0 space-y-8">
                        {overview.pending.length > 0 && (
                            <Pending
                                classroom={classroom}
                                items={overview.pending}
                            />
                        )}

                        <Section
                            title={`Próximos ${words.many}`}
                            icon={<CalendarClock />}
                        >
                            {later.length > 0 ? (
                                <ul className="divide-y overflow-hidden rounded-2xl border bg-card">
                                    {later.map((row) => (
                                        <li key={row.id}>
                                            <MeetingRow
                                                classroom={classroom}
                                                row={row}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="rounded-2xl border border-dashed p-4 text-sm text-muted-foreground">
                                    Nada marcado depois deste {words.one}.
                                </p>
                            )}
                            <Link
                                href={meetingsIndex(classroom.slug)}
                                className="mt-3 inline-flex min-h-10 items-center gap-1 text-sm font-medium text-primary hover:underline"
                            >
                                Todos os {words.many}{' '}
                                <ChevronRight className="size-4" />
                            </Link>
                        </Section>

                        {upcoming.total > 0 && (
                            <GroupMessages
                                classroom={classroom}
                                items={upcoming.data}
                            />
                        )}

                        {overview.recent.length > 0 && (
                            <Past
                                classroom={classroom}
                                items={overview.recent}
                                last={overview.last_sunday}
                            />
                        )}
                    </div>

                    <aside className="min-w-0 space-y-8" aria-label="Alunos">
                        <Attention
                            classroom={classroom}
                            items={overview.attention}
                            thresholds={overview.thresholds}
                        />

                        {overview.birthdays.length > 0 && (
                            <Birthdays
                                items={overview.birthdays}
                                today={overview.today}
                                program={words.program}
                            />
                        )}

                        <nav
                            aria-label={`Mais da classe ${classroom.name}`}
                            className="divide-y overflow-hidden rounded-2xl border bg-card text-sm"
                        >
                            <MoreLink
                                href={studentsIndex.url(classroom.slug)}
                                icon={<Users />}
                                label={`Todos os alunos (${stats.students})`}
                            />
                            <MoreLink
                                href={report.url(classroom.slug)}
                                icon={<FileText />}
                                label="Relatório"
                                hint={
                                    overview.home_study?.rate != null
                                        ? `${overview.home_study.rate}% estudaram em casa`
                                        : undefined
                                }
                            />
                            <MoreLink
                                href={questions.url(classroom.slug)}
                                icon={<MessageCircleQuestion />}
                                label="Dúvidas dos alunos"
                            />
                        </nav>
                    </aside>
                </div>
            </Page>
        </>
    );
}

function MoreLink({
    href,
    icon,
    label,
    hint,
}: {
    href: string;
    icon: ReactNode;
    label: string;
    hint?: string;
}) {
    return (
        <Link
            href={href}
            className="flex min-h-12 items-center gap-3 px-4 py-2.5 hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none [&>svg]:size-4 [&>svg]:shrink-0 [&>svg]:text-muted-foreground"
        >
            {icon}
            <span className="min-w-0 flex-1">
                <span className="block font-medium">{label}</span>
                {hint && (
                    <span className="block text-muted-foreground">{hint}</span>
                )}
            </span>
            <ChevronRight />
        </Link>
    );
}

function Pending({
    classroom,
    items,
}: {
    classroom: Classroom;
    items: Overview['pending'];
}) {
    const words = meetingWords(classroom);

    return (
        <section className="rounded-2xl border border-warning/50 bg-warning-soft p-4">
            <h2 className="flex items-center gap-2 font-semibold text-warning-foreground">
                <AlertTriangle className="size-5" /> {words.Many} para confirmar
            </h2>
            <p className="mt-1 text-sm text-warning-foreground/80">
                Já passaram e continuam “planejados”. Faça a chamada ou marque
                se teve aula.
            </p>
            <ul className="mt-3 space-y-2">
                {items.map((item) => (
                    <li key={item.id}>
                        <Link
                            href={meetingPage({
                                classroom: classroom.slug,
                                meeting: item.id,
                            })}
                            className="flex items-center gap-3 rounded-xl bg-card px-3 py-2.5 text-sm hover:bg-muted/60"
                        >
                            <span className="min-w-0 flex-1">
                                <span className="font-medium">
                                    {dayMonth(item.held_on)}
                                </span>
                                <span className="text-muted-foreground">
                                    {' · '}
                                    {item.lesson ??
                                        item.title ??
                                        'Lição a definir'}
                                </span>
                            </span>
                            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function WeekCard({
    classroom,
    week,
    here,
}: {
    classroom: Classroom;
    week: NonNullable<Overview['week']>;
    here: string;
}) {
    const { meeting, lesson, summary } = week;
    const words = meetingWords(classroom);
    const url = meetingPage({ classroom: classroom.slug, meeting: meeting.id });

    return (
        <section className="rounded-2xl border border-primary/30 bg-accent/40 p-5 sm:p-6">
            <p className="text-sm font-medium text-primary">
                {meeting.is_today
                    ? 'Hoje'
                    : `Próximo ${words.one} · ${dayMonth(meeting.held_on)}`}
            </p>
            <h2 className="mt-1 font-serif text-2xl font-semibold text-balance sm:text-3xl">
                {lesson?.display_title ?? meeting.title ?? 'Lição a definir'}
            </h2>
            <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                {week.meeting_total > 1 && (
                    <span>
                        {words.one} {week.meeting_index} de {week.meeting_total}{' '}
                        da lição
                    </span>
                )}
                {lesson?.status === 'draft' && (
                    <StatusBadge status="draft" label="Rascunho" />
                )}
            </p>

            {week.cancelled_before.map((item) => (
                <p
                    key={item.held_on}
                    className="mt-3 rounded-xl bg-muted px-3 py-2 text-sm"
                >
                    {longDate(item.held_on)}: sem {words.program}
                    {item.title ? ` (${item.title})` : ''}.
                </p>
            ))}

            {summary && (
                <p className="mt-4 flex items-center gap-2 rounded-xl bg-success-soft px-3 py-2 text-sm text-success-foreground">
                    <CalendarCheck className="size-4 shrink-0" /> Chamada feita:{' '}
                    {summary.present} de {summary.expected} presentes
                    {summary.visitors > 0 &&
                        ` · ${plural(summary.visitors, 'visitante', 'visitantes')}`}
                </p>
            )}

            <div className="mt-4 flex flex-wrap gap-2">
                {lesson && (
                    <Button asChild>
                        <Link
                            href={sunday.url(lesson.slug, {
                                query: { voltar: here },
                            })}
                        >
                            <Presentation /> Modo Domingo
                        </Link>
                    </Button>
                )}
                {lesson && meeting.is_today && !meeting.is_finished && (
                    <FinishMeetingDialog
                        meetingId={meeting.id}
                        initialNotes={meeting.notes}
                        classroom={classroom}
                    >
                        <Button variant="outline">
                            <Flag /> Encerrar aula
                        </Button>
                    </FinishMeetingDialog>
                )}
                {lesson && (
                    <Button asChild variant="outline">
                        <Link href={editLesson(lesson.id)}>
                            <Pencil /> Editar lição
                        </Link>
                    </Button>
                )}
                <Button asChild variant="outline">
                    <Link href={url}>
                        {week.can_take_attendance && !meeting.has_attendance ? (
                            <>
                                <Users /> Fazer chamada
                            </>
                        ) : (
                            <>
                                <CalendarCheck /> Ver o {words.one}
                            </>
                        )}
                    </Link>
                </Button>
            </div>

            {week.weekly_message && (
                <details className="group mt-4 rounded-xl border bg-background/60">
                    <summary className="flex cursor-pointer items-center gap-2 px-3 py-2.5 text-sm font-medium marker:content-none">
                        <MessageSquareText className="size-4 text-primary" />
                        Mensagem da semana para o grupo
                        <ChevronRight className="ml-auto size-4 text-muted-foreground transition-transform group-open:rotate-90" />
                    </summary>
                    <div className="border-t px-3 py-3">
                        <pre className="max-h-56 overflow-auto rounded-lg bg-muted/60 p-3 font-sans text-sm whitespace-pre-wrap">
                            {week.weekly_message}
                        </pre>
                        <div className="mt-3">
                            <CopyWhatsAppButtons
                                text={week.weekly_message}
                                copyLabel="Copiar mensagem"
                            />
                        </div>
                    </div>
                </details>
            )}
        </section>
    );
}

/**
 * Mensagens prontas das próximas lições, para adiantar o envio no grupo.
 * Recolhidas: só abrem quando a pessoa quer mandar.
 */
function GroupMessages({
    classroom,
    items,
}: {
    classroom: Classroom;
    items: UpcomingLesson[];
}) {
    const words = meetingWords(classroom);
    const withMessage = items.filter((item) => item.message);

    if (withMessage.length === 0) {
        return null;
    }

    return (
        <Section title="Mensagens para o grupo" icon={<MessageSquareText />}>
            <ul className="divide-y overflow-hidden rounded-2xl border bg-card">
                {withMessage.map((item) => (
                    <li key={item.lesson.id}>
                        <details className="group">
                            <summary className="flex min-h-12 cursor-pointer items-center gap-3 px-4 py-2.5 marker:content-none hover:bg-muted/50 [&::-webkit-details-marker]:hidden">
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-medium">
                                        {item.lesson.display_title}
                                    </span>
                                    <span className="block text-sm text-muted-foreground">
                                        {item.is_today
                                            ? 'Hoje'
                                            : `${classroom.meeting_weekday_label ?? words.One}, ${dayMonth(item.held_on)}`}
                                    </span>
                                </span>
                                {item.lesson.status === 'draft' && (
                                    <StatusBadge
                                        status="draft"
                                        label="Rascunho"
                                    />
                                )}
                                <ChevronRight className="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-90" />
                            </summary>
                            <div className="px-4 pb-4">
                                {item.lesson.status === 'draft' && (
                                    <p className="mb-2 text-sm text-warning-foreground">
                                        Rascunho: publique a lição antes de
                                        mandar, senão os alunos não conseguem
                                        abrir o link.
                                    </p>
                                )}
                                <pre className="max-h-56 overflow-auto rounded-lg bg-muted/60 p-3 font-sans text-sm whitespace-pre-wrap">
                                    {item.message}
                                </pre>
                                <div className="mt-3">
                                    <CopyWhatsAppButtons
                                        text={item.message ?? ''}
                                        copyLabel="Copiar mensagem"
                                    />
                                </div>
                            </div>
                        </details>
                    </li>
                ))}
            </ul>
        </Section>
    );
}

/** Domingos que já foram: presença de cada um e "onde paramos" no último. */
function Past({
    classroom,
    items,
    last,
}: {
    classroom: Classroom;
    items: SundayRow[];
    last: Overview['last_sunday'];
}) {
    const words = meetingWords(classroom);

    return (
        <Section title={`Já foram`} icon={<CalendarCheck />}>
            {last?.notes && (
                <p className="mb-3 flex gap-2 rounded-xl bg-highlight/50 px-3 py-2 text-sm text-highlight-foreground">
                    <StickyNote className="mt-0.5 size-4 shrink-0" />
                    <span>
                        <span className="font-medium">Onde paramos:</span>{' '}
                        {last.notes}
                    </span>
                </p>
            )}
            <ul className="divide-y overflow-hidden rounded-2xl border bg-card">
                {items.map((item) => (
                    <li key={item.id}>
                        <Link
                            href={meetingPage({
                                classroom: classroom.slug,
                                meeting: item.id,
                            })}
                            className="flex min-h-12 items-center gap-3 px-4 py-2.5 text-sm hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
                        >
                            <span className="w-20 shrink-0 whitespace-nowrap text-muted-foreground tabular-nums">
                                {dayMonth(item.held_on)}
                            </span>
                            <span className="min-w-0 flex-1 truncate">
                                {item.lesson ?? item.title ?? 'Sem lição'}
                            </span>
                            <span className="shrink-0 tabular-nums">
                                {item.present} de {item.expected}
                            </span>
                            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                        </Link>
                    </li>
                ))}
            </ul>
            <p className="mt-2 text-xs text-muted-foreground">
                Presentes em cada {words.one}, sem contar visitantes.
            </p>
        </Section>
    );
}

function Attention({
    classroom,
    items,
    thresholds,
}: {
    classroom: Classroom;
    items: Overview['attention'];
    thresholds: Overview['thresholds'];
}) {
    return (
        <Section
            title="Precisam de atenção"
            icon={<AlertTriangle />}
            description={`${thresholds.missed_meetings} faltas seguidas ou ${thresholds.inactive_days} dias sem leitura. Quem entrou há menos de ${thresholds.new_student_days} dias fica de fora.`}
        >
            {items.length === 0 ? (
                <p className="rounded-2xl border border-dashed p-4 text-sm text-muted-foreground">
                    Ninguém por enquanto.
                </p>
            ) : (
                <ul className="divide-y rounded-2xl border bg-card">
                    {items.map((student) => (
                        <li
                            key={student.id}
                            className="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3"
                        >
                            <Link
                                href={studentPage({
                                    classroom: classroom.slug,
                                    user: student.id,
                                })}
                                className="min-w-36 flex-1 hover:underline"
                            >
                                <span className="block truncate font-medium">
                                    {student.name}
                                </span>
                                <span className="block text-sm text-muted-foreground">
                                    {student.reasons.join(' · ')}
                                    {student.last_present_on &&
                                        ` · última presença ${dayMonth(student.last_present_on)}`}
                                </span>
                            </Link>
                            <WhatsAppButton
                                phone={student.phone}
                                text={missYouMessage(
                                    student.name,
                                    classroom.name,
                                    meetingWords(classroom).program,
                                )}
                                variant="outline"
                            />
                        </li>
                    ))}
                </ul>
            )}
        </Section>
    );
}

function Birthdays({
    items,
    today,
    program,
}: {
    items: Overview['birthdays'];
    today: string;
    program: string;
}) {
    return (
        <Section title="Aniversariantes do mês" icon={<Cake />}>
            <ul className="divide-y rounded-2xl border bg-card">
                {items.map((person) => {
                    const date = `${today.slice(0, 8)}${String(person.day).padStart(2, '0')}`;
                    const tile = dateTile(date);

                    return (
                        <li
                            key={person.id}
                            className="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3"
                        >
                            <div className="w-10 shrink-0 text-center">
                                <p className="text-xs text-muted-foreground uppercase">
                                    {tile.weekday}
                                </p>
                                <p className="font-serif text-xl font-semibold tabular-nums">
                                    {person.day}
                                </p>
                            </div>
                            <div className="min-w-24 flex-1">
                                <p className="truncate font-medium">
                                    {person.name}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {person.is_today
                                        ? `Hoje! Faz ${person.turning} anos 🎉`
                                        : `${date < today ? 'Fez' : 'Faz'} ${person.turning} anos · ${relativeDay(date, today)}`}
                                </p>
                            </div>
                            <WhatsAppButton
                                phone={person.phone}
                                text={birthdayMessage(person.name, program)}
                                label="Parabéns"
                                variant={
                                    person.is_today ? 'default' : 'outline'
                                }
                            />
                        </li>
                    );
                })}
            </ul>
        </Section>
    );
}
