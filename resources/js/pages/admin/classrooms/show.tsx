import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    BookOpen,
    CalendarCheck,
    CalendarClock,
    Cake,
    ChevronRight,
    FileText,
    MessageSquareText,
    Pencil,
    Presentation,
    StickyNote,
    Users,
} from 'lucide-react';
import { ClassroomHeader } from '@/components/admin/classroom-header';
import { Meter } from '@/components/admin/meter';
import { StatTile } from '@/components/admin/stat-tile';
import { StatusBadge } from '@/components/admin/status-badge';
import { CopyWhatsAppButtons } from '@/components/copy-whatsapp-button';
import { EmptyState, Page, PageHeader, Section } from '@/components/page';
import { Button } from '@/components/ui/button';
import { WhatsAppButton } from '@/components/whatsapp-button';
import { dateTile, dayMonth, longDate, relativeDay } from '@/lib/dates';
import { birthdayMessage, missYouMessage } from '@/lib/whatsapp';
import { edit, report, show as classroomPage } from '@/routes/admin/classrooms';
import {
    index as meetingsIndex,
    show as meetingPage,
} from '@/routes/admin/classrooms/meetings';
import { show as studentPage } from '@/routes/admin/classrooms/students';
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
        period: { label: string; series_id: number | null };
    };
    thresholds: {
        missed_meetings: number;
        inactive_days: number;
        new_student_days: number;
    };
};

type Props = {
    classroom: Classroom;
    overview: Overview;
    canEdit: boolean;
};

const plural = (count: number, one: string, many: string) =>
    `${count} ${count === 1 ? one : many}`;

/**
 * Resumo da classe: o que fazer agora (este domingo, pendências, quem precisa
 * de atenção) e como a classe está indo.
 */
export default function ClassroomOverview({
    classroom,
    overview,
    canEdit,
}: Props) {
    const { week, last_sunday: last, stats } = overview;
    const here = classroomPage.url(classroom.slug);

    return (
        <>
            <Head title={classroom.name} />

            <Page width="wide">
                <ClassroomHeader classroom={classroom} active="resumo" />
                <PageHeader
                    title={classroom.name}
                    description={`${plural(stats.students, 'aluno', 'alunos')}${stats.period.series_id ? ` · ${stats.period.label}` : ''}`}
                    actions={
                        canEdit && (
                            <Button asChild variant="ghost">
                                <Link href={edit(classroom.slug)}>
                                    <Pencil /> Editar classe
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="space-y-6">
                        {week ? (
                            <WeekCard
                                classroom={classroom}
                                week={week}
                                here={here}
                            />
                        ) : (
                            <EmptyState
                                icon={<CalendarClock />}
                                title="Nenhum domingo planejado"
                            >
                                <p>
                                    Planeje os próximos domingos para a classe
                                    saber o que estudar.
                                </p>
                                <Button asChild className="mt-4">
                                    <Link href={meetingsIndex(classroom.slug)}>
                                        Abrir Domingos
                                    </Link>
                                </Button>
                            </EmptyState>
                        )}

                        {last && (
                            <LastSunday classroom={classroom} last={last} />
                        )}

                        {overview.pending.length > 0 && (
                            <section className="rounded-2xl border border-warning/50 bg-warning-soft p-4">
                                <h2 className="flex items-center gap-2 font-semibold text-warning-foreground">
                                    <AlertTriangle className="size-5" />{' '}
                                    Domingos para confirmar
                                </h2>
                                <p className="mt-1 text-sm text-warning-foreground/80">
                                    Já passaram e continuam “planejados”. Faça a
                                    chamada ou marque se teve aula.
                                </p>
                                <ul className="mt-3 space-y-2">
                                    {overview.pending.map((item) => (
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
                        )}
                    </div>

                    <div className="space-y-6">
                        <Attention
                            classroom={classroom}
                            items={overview.attention}
                            thresholds={overview.thresholds}
                        />

                        {overview.birthdays.length > 0 && (
                            <Birthdays
                                items={overview.birthdays}
                                today={overview.today}
                            />
                        )}
                    </div>
                </div>

                <Section
                    title="Números da classe"
                    className="mt-10"
                    description={`Frequência ${stats.period.series_id ? `na série ${stats.period.label}` : `nos ${stats.period.label.toLowerCase()}`}. Cada aluno conta a partir de quando entrou na classe; visitantes ficam fora da conta.`}
                >
                    <div className="grid gap-3 sm:grid-cols-3">
                        <StatTile
                            icon={<CalendarCheck />}
                            label="Frequência"
                            value={
                                stats.frequency.rate === null
                                    ? '—'
                                    : `${stats.frequency.rate}%`
                            }
                            hint={
                                stats.frequency.sundays > 0
                                    ? `${stats.frequency.present} de ${stats.frequency.expected} presenças em ${plural(stats.frequency.sundays, 'domingo', 'domingos')}`
                                    : 'Nenhuma chamada no período'
                            }
                        />
                        <StatTile
                            icon={<BookOpen />}
                            label="Estudo em casa"
                            value={
                                overview.home_study?.rate == null
                                    ? '—'
                                    : `${overview.home_study.rate}%`
                            }
                            hint={
                                overview.home_study
                                    ? `${overview.home_study.readers} de ${overview.home_study.expected} leram a lição atual`
                                    : 'Sem lição na semana'
                            }
                        />
                        <StatTile
                            icon={<Users />}
                            label="Alunos"
                            value={String(stats.students)}
                            hint={
                                overview.attention.length > 0
                                    ? `${overview.attention.length} precisam de atenção`
                                    : 'Ninguém precisando de atenção'
                            }
                            tone={
                                overview.attention.length > 0
                                    ? 'warning'
                                    : 'default'
                            }
                        />
                    </div>
                    <Button asChild variant="outline" className="mt-4">
                        <Link href={report(classroom.slug)}>
                            <FileText /> Ver o relatório completo
                        </Link>
                    </Button>
                </Section>
            </Page>
        </>
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
    const url = meetingPage({ classroom: classroom.slug, meeting: meeting.id });

    return (
        <section className="rounded-2xl border bg-card p-5">
            <p className="text-sm font-medium text-primary">
                {meeting.is_today
                    ? 'Hoje'
                    : `Próximo domingo · ${dayMonth(meeting.held_on)}`}
            </p>
            <h2 className="mt-1 font-serif text-2xl font-semibold text-balance">
                {lesson?.display_title ?? meeting.title ?? 'Lição a definir'}
            </h2>
            <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                {week.meeting_total > 1 && (
                    <span>
                        domingo {week.meeting_index} de {week.meeting_total} da
                        lição
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
                    {longDate(item.held_on)}: sem EBD
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
                <Button asChild variant="outline">
                    <Link href={url}>
                        {week.can_take_attendance && !meeting.has_attendance ? (
                            <>
                                <Users /> Fazer chamada
                            </>
                        ) : (
                            <>
                                <CalendarCheck /> Ver o domingo
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

function LastSunday({
    classroom,
    last,
}: {
    classroom: Classroom;
    last: NonNullable<Overview['last_sunday']>;
}) {
    return (
        <section className="rounded-2xl border bg-card p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-sm font-medium text-muted-foreground">
                        Último domingo · {dayMonth(last.held_on)}
                    </p>
                    <p className="mt-0.5 font-medium">
                        {last.lesson ?? last.title ?? 'Sem lição'}
                    </p>
                </div>
                <Button asChild variant="ghost" size="sm">
                    <Link
                        href={meetingPage({
                            classroom: classroom.slug,
                            meeting: last.id,
                        })}
                    >
                        Ver <ChevronRight />
                    </Link>
                </Button>
            </div>
            <p className="mt-3 text-sm">
                <span className="font-semibold">
                    {last.present} de {last.expected}
                </span>{' '}
                presentes
                {last.rate !== null && ` (${last.rate}%)`}
                {last.visitors > 0 &&
                    ` · ${plural(last.visitors, 'visitante', 'visitantes')}`}
            </p>
            <Meter
                value={last.rate}
                className="mt-2"
                label={`${last.rate ?? 0}% de presença`}
            />
            {last.notes && (
                <p className="mt-4 flex gap-2 rounded-xl bg-highlight/50 px-3 py-2 text-sm text-highlight-foreground">
                    <StickyNote className="mt-0.5 size-4 shrink-0" />
                    <span>
                        <span className="font-medium">Onde paramos:</span>{' '}
                        {last.notes}
                    </span>
                </p>
            )}
        </section>
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
                            className="flex items-center gap-3 px-4 py-3"
                        >
                            <Link
                                href={studentPage({
                                    classroom: classroom.slug,
                                    user: student.id,
                                })}
                                className="min-w-0 flex-1 hover:underline"
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
                                )}
                                variant="ghost"
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
}: {
    items: Overview['birthdays'];
    today: string;
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
                            className="flex items-center gap-3 px-4 py-3"
                        >
                            <div className="w-10 shrink-0 text-center">
                                <p className="text-xs text-muted-foreground uppercase">
                                    {tile.weekday}
                                </p>
                                <p className="font-serif text-xl font-semibold tabular-nums">
                                    {person.day}
                                </p>
                            </div>
                            <div className="min-w-0 flex-1">
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
                                text={birthdayMessage(person.name)}
                                label="Parabéns"
                                variant={person.is_today ? 'default' : 'ghost'}
                            />
                        </li>
                    );
                })}
            </ul>
        </Section>
    );
}
