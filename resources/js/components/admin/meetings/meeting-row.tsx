import { Link } from '@inertiajs/react';
import { ChevronRight, StickyNote } from 'lucide-react';
import { MeetingStatusBadge } from '@/components/admin/meeting-status-badge';
import { StatusBadge } from '@/components/admin/status-badge';
import { dateTile } from '@/lib/dates';
import { meetingWords } from '@/lib/meeting-words';
import { cn } from '@/lib/utils';
import { show } from '@/routes/admin/classrooms/meetings';
import type {
    Classroom,
    LessonStatus,
    MeetingStatus,
    SundaySummary,
} from '@/types';

/** Um encontro da agenda (MeetingRows no servidor). */
export type MeetingRowData = {
    id: number;
    held_on: string;
    status: MeetingStatus;
    status_label: string;
    title: string | null;
    lesson: {
        id: number;
        slug: string;
        display_title: string;
        status: LessonStatus;
    } | null;
    has_attendance: boolean;
    summary: SundaySummary | null;
    position: { index: number; total: number } | null;
    is_today: boolean;
    awaiting_confirmation: boolean;
    has_notes: boolean;
};

/** Linha de um encontro: abre a página dele (lição, chamada, "onde paramos"). */
export function MeetingRow({
    classroom,
    row,
}: {
    classroom: Classroom;
    row: MeetingRowData;
}) {
    const words = meetingWords(classroom);
    const tile = dateTile(row.held_on);
    const cancelled = row.status === 'cancelled';
    const title = cancelled
        ? `Sem ${words.program}${row.title ? ` · ${row.title}` : ''}`
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
                            {words.one} {row.position.index} de{' '}
                            {row.position.total}
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
            {/* Planejado/Realizado já estão ditos pela seção; o selo só aparece quando informa. */}
            {cancelled && (
                <MeetingStatusBadge
                    status={row.status}
                    label={row.status_label}
                    className="hidden sm:inline-flex"
                />
            )}
            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
        </Link>
    );
}
