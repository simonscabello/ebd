import { Head, Link } from '@inertiajs/react';
import { CalendarPlus, ChevronRight, StickyNote } from 'lucide-react';
import { ClassroomHeader } from '@/components/admin/classroom-header';
import { AddMeetingDialog } from '@/components/admin/meetings/add-meeting-dialog';
import { PlanMeetingsDialog } from '@/components/admin/meetings/plan-meetings-dialog';
import { StatusBadge } from '@/components/admin/status-badge';
import { MeetingStatusBadge } from '@/components/admin/meeting-status-badge';
import { EmptyState, Page, PageHeader, Section } from '@/components/page';
import { dateTile, monthYear } from '@/lib/dates';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/admin/classrooms/meetings';
import type {
    Classroom,
    LessonOption,
    LessonStatus,
    MeetingStatus,
    Series,
    SundaySummary,
} from '@/types';

type Row = {
    id: number;
    held_on: string;
    status: MeetingStatus;
    status_label: string;
    title: string | null;
    lesson: { id: number; display_title: string; status: LessonStatus } | null;
    has_attendance: boolean;
    summary: SundaySummary | null;
    position: { index: number; total: number } | null;
    is_today: boolean;
    awaiting_confirmation: boolean;
    has_notes: boolean;
};

type Props = {
    classroom: Classroom;
    today: string;
    nextSunday: string;
    upcoming: Row[];
    past: Row[];
    showingAll: boolean;
    hasOlder: boolean;
    lessons: LessonOption[];
    series: Series[];
};

/**
 * Domingos da classe: os próximos primeiro e os anteriores abaixo. Cada linha
 * abre a página do domingo, onde ficam a lição, a chamada e "onde paramos".
 */
export default function ClassroomMeetings({
    classroom,
    nextSunday,
    upcoming,
    past,
    showingAll,
    hasOlder,
    lessons,
    series,
}: Props) {
    return (
        <>
            <Head title={`Domingos · ${classroom.name}`} />

            <Page width="wide">
                <ClassroomHeader classroom={classroom} active="domingos" />
                <PageHeader
                    title="Domingos"
                    description="A lição de cada domingo, a chamada e onde a turma parou. Uma lição pode ocupar mais de um domingo."
                    actions={
                        <>
                            <PlanMeetingsDialog
                                classroom={classroom}
                                series={series}
                                nextSunday={nextSunday}
                            />
                            <AddMeetingDialog
                                classroom={classroom}
                                lessons={lessons}
                                nextSunday={nextSunday}
                            />
                        </>
                    }
                />

                {upcoming.length === 0 && past.length === 0 ? (
                    <EmptyState
                        icon={<CalendarPlus />}
                        title="Nenhum domingo na agenda"
                    >
                        Use “Planejar série” para criar os domingos e distribuir
                        as lições da revista.
                    </EmptyState>
                ) : (
                    <div className="space-y-10">
                        <Section title="Próximos">
                            {upcoming.length === 0 ? (
                                <p className="rounded-2xl border border-dashed p-4 text-sm text-muted-foreground">
                                    Nenhum domingo planejado daqui para frente.
                                    Use “Planejar série” ou “Adicionar domingo”.
                                </p>
                            ) : (
                                <MeetingList
                                    classroom={classroom}
                                    rows={upcoming}
                                />
                            )}
                        </Section>

                        {past.length > 0 && (
                            <Section title="Anteriores">
                                <MeetingList
                                    classroom={classroom}
                                    rows={past}
                                />
                                {hasOlder && !showingAll && (
                                    <Link
                                        href={index.url(classroom.slug, {
                                            query: { todos: 1 },
                                        })}
                                        preserveScroll
                                        className="mt-3 inline-flex text-sm font-medium text-primary hover:underline"
                                    >
                                        Ver domingos mais antigos
                                    </Link>
                                )}
                            </Section>
                        )}
                    </div>
                )}
            </Page>
        </>
    );
}

function MeetingList({
    classroom,
    rows,
}: {
    classroom: Classroom;
    rows: Row[];
}) {
    let month = '';

    return (
        <ul className="divide-y rounded-2xl border bg-card">
            {rows.map((row, index) => {
                const rowMonth = monthYear(row.held_on);
                const showMonth = rowMonth !== month;
                month = rowMonth;

                return (
                    <li key={row.id}>
                        {showMonth && (
                            <p
                                className={cn(
                                    'bg-muted/40 px-4 py-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase',
                                    index === 0 && 'rounded-t-2xl',
                                )}
                            >
                                {rowMonth}
                            </p>
                        )}
                        <MeetingRow classroom={classroom} row={row} />
                    </li>
                );
            })}
        </ul>
    );
}

function MeetingRow({ classroom, row }: { classroom: Classroom; row: Row }) {
    const tile = dateTile(row.held_on);
    const cancelled = row.status === 'cancelled';
    const title = cancelled
        ? `Sem EBD${row.title ? ` · ${row.title}` : ''}`
        : (row.lesson?.display_title ?? row.title ?? 'Lição a definir');

    return (
        <Link
            href={show({ classroom: classroom.slug, meeting: row.id })}
            className={cn(
                'flex items-center gap-4 px-4 py-3 hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none',
                cancelled && 'text-muted-foreground',
            )}
        >
            <div className="w-12 shrink-0 text-center">
                <p className="text-xs text-muted-foreground uppercase">
                    {tile.weekday}
                </p>
                <p
                    className={cn(
                        'font-serif text-2xl font-semibold tabular-nums',
                        cancelled && 'line-through',
                    )}
                >
                    {tile.day}
                </p>
            </div>
            <div className="min-w-0 flex-1">
                <p
                    className={cn(
                        'line-clamp-2 font-medium',
                        !row.lesson && !cancelled && 'text-muted-foreground',
                    )}
                >
                    {title}
                </p>
                <p className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                    {row.is_today && (
                        <span className="font-medium text-primary">Hoje</span>
                    )}
                    {row.summary && (
                        <span>
                            {row.summary.present} de {row.summary.expected}{' '}
                            presentes
                            {row.summary.visitors > 0 &&
                                ` · ${row.summary.visitors} visitante${row.summary.visitors > 1 ? 's' : ''}`}
                        </span>
                    )}
                    {row.awaiting_confirmation && (
                        <span className="font-medium text-warning-foreground">
                            Chamada pendente
                        </span>
                    )}
                    {row.position && row.position.total > 1 && (
                        <span>
                            domingo {row.position.index} de {row.position.total}
                        </span>
                    )}
                    {row.lesson?.status === 'draft' && (
                        <StatusBadge status="draft" label="Lição em rascunho" />
                    )}
                    {row.has_notes && (
                        <StickyNote
                            className="size-3.5"
                            aria-label="Tem anotação"
                        />
                    )}
                </p>
            </div>
            <MeetingStatusBadge
                status={row.status}
                label={row.status_label}
                className="hidden sm:inline-flex"
            />
            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
        </Link>
    );
}
