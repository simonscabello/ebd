import { Head, Link } from '@inertiajs/react';
import {
    CalendarPlus,
    ChevronDown,
    Ellipsis,
    MessageSquareText,
    Presentation,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ClassroomTabs } from '@/components/admin/classroom-header';
import type { MeetingRowData } from '@/components/admin/meetings/meeting-row';
import { PlanMeetingsDialog } from '@/components/admin/meetings/plan-meetings-dialog';
import { StatusBadge } from '@/components/admin/status-badge';
import { CopyWhatsAppButtons } from '@/components/copy-whatsapp-button';
import { EmptyState, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { WhatsAppButton } from '@/components/whatsapp-button';
import { dateTile, dayMonth, relativeDay } from '@/lib/dates';
import { meetingWords } from '@/lib/meeting-words';
import { cn, plural } from '@/lib/utils';
import { missYouMessage } from '@/lib/whatsapp';
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
import { show as studentPage } from '@/routes/admin/classrooms/students';
import { edit as editLesson } from '@/routes/admin/lessons';
import { sunday } from '@/routes/lessons';
import type { Classroom, Series } from '@/types';

type Row = MeetingRowData & {
    absent: {
        id: number;
        name: string;
        phone: string | null;
        missed_in_a_row: number;
    }[];
};

/** Só o que a agenda usa do resumo da classe (ClassroomOverviewQuery). */
type Overview = {
    today: string;
    week: {
        meeting: { id: number };
        weekly_message: string | null;
    } | null;
    home_study: { readers: number; expected: number } | null;
    stats: { students: number };
};

type UpcomingLesson = { meeting_id: number; message: string | null };

type Props = {
    classroom: Classroom;
    overview: Overview;
    upcoming: { data: UpcomingLesson[] };
    sundays: Row[];
    past: Row[];
    nextSunday: string;
    series: Series[];
    canEdit: boolean;
};

/**
 * Página da classe: a agenda. Cada data mostra a ação que falta (apresentar a
 * lição, terminar o rascunho, escolher a lição, fazer a chamada, falar com
 * quem faltou). O resto da classe fica em Alunos e no menu "⋯".
 */
export default function ClassroomAgenda({
    classroom,
    overview,
    upcoming,
    sundays,
    past,
    nextSunday,
    series,
    canEdit,
}: Props) {
    const words = meetingWords(classroom);
    const here = classroomPage.url(classroom.slug);

    // Datas sem lição: duas bastam para lembrar de planejar; o resto é ruído.
    let undefinedShown = 0;
    const next = sundays.filter(
        (row) =>
            row.lesson !== null ||
            row.status === 'cancelled' ||
            undefinedShown++ < 2,
    );
    const highlightId = next.find((row) => row.status !== 'cancelled')?.id;

    // Mensagem pronta para o grupo, no primeiro encontro de cada lição.
    const messages = new Map<number, string>();
    for (const item of upcoming.data) {
        if (item.message) {
            messages.set(item.meeting_id, item.message);
        }
    }
    if (overview.week?.weekly_message) {
        messages.set(overview.week.meeting.id, overview.week.weekly_message);
    }

    const plan = (
        <PlanMeetingsDialog
            classroom={classroom}
            series={series}
            nextSunday={nextSunday}
        />
    );

    return (
        <>
            <Head title={classroom.name} />

            <Page width="wide">
                <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">
                        <h1 className="font-serif text-3xl font-semibold tracking-tight">
                            {classroom.name}
                        </h1>
                        <ClassroomTabs
                            classroom={classroom}
                            active="agenda"
                            students={overview.stats.students}
                        />
                    </div>
                    <div className="flex gap-2">
                        {plan}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    aria-label={`Mais da classe ${classroom.name}`}
                                >
                                    <Ellipsis />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <MenuLink
                                    href={meetingsIndex.url(classroom.slug)}
                                >
                                    Todas as datas
                                </MenuLink>
                                <MenuLink href={report.url(classroom.slug)}>
                                    Relatório
                                </MenuLink>
                                <MenuLink href={questions.url(classroom.slug)}>
                                    Dúvidas dos alunos
                                </MenuLink>
                                {canEdit && (
                                    <MenuLink href={edit.url(classroom.slug)}>
                                        Editar classe
                                    </MenuLink>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </header>

                {next.length === 0 && past.length === 0 ? (
                    <EmptyState
                        icon={<CalendarPlus />}
                        title={`Nenhum ${words.one} na agenda`}
                    >
                        <p>
                            Planeje a série para distribuir as lições da revista
                            pelos {words.many}.
                        </p>
                        <div className="mt-4">{plan}</div>
                    </EmptyState>
                ) : (
                    <div className="overflow-hidden rounded-2xl border bg-card">
                        <GroupLabel>Próximos</GroupLabel>
                        {next.length === 0 ? (
                            <p className="px-4 pb-4 text-sm text-muted-foreground">
                                Nada marcado daqui para frente.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {next.map((row) => (
                                    <AgendaRow
                                        key={row.id}
                                        classroom={classroom}
                                        row={row}
                                        today={overview.today}
                                        here={here}
                                        highlight={row.id === highlightId}
                                        message={messages.get(row.id)}
                                        readers={
                                            row.id === overview.week?.meeting.id
                                                ? overview.home_study
                                                : null
                                        }
                                    />
                                ))}
                            </ul>
                        )}

                        {past.length > 0 && (
                            <>
                                <GroupLabel className="border-t">
                                    Já foram
                                </GroupLabel>
                                <ul className="divide-y">
                                    {past.map((row) => (
                                        <AgendaRow
                                            key={row.id}
                                            classroom={classroom}
                                            row={row}
                                            today={overview.today}
                                            here={here}
                                        />
                                    ))}
                                </ul>
                            </>
                        )}
                    </div>
                )}

                <Link
                    href={meetingsIndex.url(classroom.slug)}
                    className="mt-3 inline-flex min-h-10 items-center text-sm font-medium text-primary hover:underline"
                >
                    Datas anteriores e todos os {words.many}
                </Link>
            </Page>
        </>
    );
}

function GroupLabel({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <h2
            className={cn(
                'px-4 pt-4 pb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase',
                className,
            )}
        >
            {children}
        </h2>
    );
}

function MenuLink({ href, children }: { href: string; children: ReactNode }) {
    return (
        <DropdownMenuItem asChild>
            <Link href={href}>{children}</Link>
        </DropdownMenuItem>
    );
}

/**
 * Uma data da agenda: quando, qual lição, como está e a ação que falta.
 * Futuras: apresentar, mandar a mensagem, terminar ou escolher a lição.
 * Passadas: a chamada (ou "fazer a chamada", se ficou pendente) e quem faltou.
 */
function AgendaRow({
    classroom,
    row,
    today,
    here,
    highlight = false,
    message,
    readers = null,
}: {
    classroom: Classroom;
    row: Row;
    today: string;
    here: string;
    highlight?: boolean;
    message?: string;
    readers?: { readers: number; expected: number } | null;
}) {
    const [absentOpen, setAbsentOpen] = useState(false);
    const words = meetingWords(classroom);
    const cancelled = row.status === 'cancelled';
    const future = row.held_on > today;
    const url = meetingPage.url({ classroom: classroom.slug, meeting: row.id });
    const lesson = row.lesson;
    const date = `${dateTile(row.held_on).weekday}, ${dayMonth(row.held_on)}`;
    const when = row.is_today ? 'hoje' : relativeDay(row.held_on, today);
    const title = cancelled
        ? `Sem ${words.program}${row.title ? ` · ${row.title}` : ''}`
        : (lesson?.display_title ?? row.title ?? 'Sem lição');
    const needsAttendance =
        !cancelled &&
        !row.has_attendance &&
        (row.is_today || row.awaiting_confirmation);

    const details = [
        row.position &&
            row.position.total > 1 &&
            `${words.one} ${row.position.index} de ${row.position.total}`,
        readers && `${readers.readers} de ${readers.expected} já leram`,
        row.summary &&
            `${row.summary.present} de ${row.summary.expected} presentes${
                row.summary.visitors > 0
                    ? ` · ${plural(row.summary.visitors, 'visitante', 'visitantes')}`
                    : ''
            }`,
        row.awaiting_confirmation && !row.has_attendance && 'chamada pendente',
    ].filter(Boolean);

    const actions: ReactNode[] = [];

    if (needsAttendance) {
        actions.push(
            <Button key="chamada" asChild>
                <Link href={url}>Fazer chamada</Link>
            </Button>,
        );
    }

    if (!cancelled && (future || row.is_today)) {
        if (!lesson) {
            actions.push(
                <Button key="escolher" asChild variant="outline">
                    <Link href={url}>Escolher lição</Link>
                </Button>,
            );
        } else if (lesson.status === 'draft') {
            actions.push(
                <Button key="terminar" asChild variant="outline">
                    <Link href={editLesson.url(lesson.id)}>Terminar lição</Link>
                </Button>,
            );
        } else if (highlight) {
            actions.push(
                <Button
                    key="apresentar"
                    asChild
                    variant={needsAttendance ? 'outline' : 'default'}
                >
                    <Link
                        href={sunday.url(lesson.slug, {
                            query: { voltar: here },
                        })}
                    >
                        <Presentation /> Apresentar lição
                    </Link>
                </Button>,
            );
        }

        if (lesson?.status === 'published' && message) {
            actions.push(
                <MessageDialog
                    key="mensagem"
                    title={lesson.display_title}
                    text={message}
                />,
            );
        }
    }

    if (!future && !row.is_today && row.has_attendance) {
        actions.push(
            row.absent.length > 0 ? (
                <Button
                    key="ausentes"
                    variant="outline"
                    aria-expanded={absentOpen}
                    onClick={() => setAbsentOpen((open) => !open)}
                    className="border-warning/50 text-warning-foreground"
                >
                    Ausentes ({row.absent.length})
                    <ChevronDown
                        className={cn(
                            'transition-transform',
                            absentOpen && 'rotate-180',
                        )}
                    />
                </Button>
            ) : (
                <span
                    key="todos"
                    className="self-center px-1 text-sm text-muted-foreground"
                >
                    Todos vieram
                </span>
            ),
            <Button key="ver" asChild variant="outline">
                <Link href={url}>Chamada</Link>
            </Button>,
        );
    }

    return (
        <li
            className={cn(
                'px-4 py-3 sm:grid sm:grid-cols-[7rem_minmax(0,1fr)_auto] sm:items-center sm:gap-4',
                highlight && 'bg-accent/40',
            )}
        >
            <p className="hidden text-sm font-semibold sm:block">
                {date}
                {(future || row.is_today) && (
                    <span className="block text-xs font-normal text-muted-foreground">
                        {when}
                    </span>
                )}
            </p>
            <div className="min-w-0">
                <div className="flex items-baseline justify-between gap-3">
                    <Link
                        href={url}
                        className={cn(
                            'min-w-0 font-medium hover:underline',
                            (cancelled || (!lesson && !row.title)) &&
                                'text-muted-foreground',
                        )}
                    >
                        {title}
                        {lesson?.status === 'draft' && (
                            <StatusBadge
                                status="draft"
                                label="Rascunho"
                                className="ml-2 align-middle"
                            />
                        )}
                    </Link>
                    <span className="shrink-0 text-xs font-semibold text-muted-foreground sm:hidden">
                        {row.is_today ? 'hoje' : date}
                    </span>
                </div>
                {details.length > 0 && (
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        {details.join(' · ')}
                    </p>
                )}
            </div>
            {(actions.length > 0 || future) && (
                <div className="mt-3 flex gap-2 sm:mt-0 sm:flex-wrap sm:justify-end max-sm:[&>*:first-child]:flex-1 max-sm:[&_svg.lucide-presentation]:hidden max-sm:[&_svg.lucide-message-square-text]:hidden">
                    {actions}
                    {future && (
                        <RowMenu
                            url={url}
                            lessonId={lesson?.id ?? null}
                            label={`Mais ações de ${date}`}
                            words={words}
                        />
                    )}
                </div>
            )}
            {absentOpen && (
                <ul className="mt-3 space-y-2 sm:col-start-2 sm:col-end-4">
                    {row.absent.map((student) => (
                        <li
                            key={student.id}
                            className="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl bg-warning-soft/60 px-3 py-2 text-sm"
                        >
                            <Link
                                href={studentPage.url({
                                    classroom: classroom.slug,
                                    user: student.id,
                                })}
                                className="min-w-0 flex-1 hover:underline"
                            >
                                <span className="font-medium">
                                    {student.name}
                                </span>
                                {student.missed_in_a_row > 1 && (
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · faltou os {
                                            student.missed_in_a_row
                                        }{' '}
                                        últimos
                                    </span>
                                )}
                            </Link>
                            <WhatsAppButton
                                phone={student.phone}
                                text={missYouMessage(
                                    student.name,
                                    classroom.name,
                                    words.program,
                                )}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </li>
    );
}

function RowMenu({
    url,
    lessonId,
    label,
    words,
}: {
    url: string;
    lessonId: number | null;
    label: string;
    words: ReturnType<typeof meetingWords>;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="icon" aria-label={label}>
                    <Ellipsis />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <MenuLink href={url}>
                    Abrir o {words.one} (trocar lição, sem {words.program})
                </MenuLink>
                {lessonId !== null && (
                    <MenuLink href={editLesson.url(lessonId)}>
                        Editar lição
                    </MenuLink>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** Mensagem pronta da lição para o grupo da classe. */
function MessageDialog({ title, text }: { title: string; text: string }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <MessageSquareText />
                    <span>
                        Mensagem
                        <span className="hidden sm:inline"> do grupo</span>
                    </span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Mensagem para o grupo</DialogTitle>
                    <DialogDescription>{title}</DialogDescription>
                </DialogHeader>
                <pre className="max-h-72 overflow-auto rounded-lg bg-muted/60 p-3 font-sans text-sm whitespace-pre-wrap">
                    {text}
                </pre>
                <CopyWhatsAppButtons text={text} copyLabel="Copiar mensagem" />
            </DialogContent>
        </Dialog>
    );
}
