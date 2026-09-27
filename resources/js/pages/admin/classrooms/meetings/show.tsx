import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BookOpen,
    CalendarCheck,
    CalendarClock,
    CalendarOff,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ChevronsRight,
    ExternalLink,
    Pencil,
    Presentation,
    RotateCcw,
    StickyNote,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { ClassroomHeader } from '@/components/admin/classroom-header';
import { CancelMeetingDialog } from '@/components/admin/meetings/cancel-meeting-dialog';
import { EditMeetingDialog } from '@/components/admin/meetings/edit-meeting-dialog';
import { MeetingStatusBadge } from '@/components/admin/meeting-status-badge';
import { useConfirm } from '@/components/confirm-dialog';
import type { Roster } from '@/components/lesson/attendance-sheet';
import { AttendanceSheet } from '@/components/lesson/attendance-sheet';
import { Page, PageHeader, Section } from '@/components/page';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { dayMonth, longDate } from '@/lib/dates';
import { show } from '@/routes/admin/classrooms/meetings';
import { edit as editLesson } from '@/routes/admin/lessons';
import {
    continueMethod as continueMeeting,
    destroy,
    held,
    restore,
    update,
} from '@/routes/admin/meetings';
import { sunday } from '@/routes/lessons';
import type {
    ClassMeeting,
    Classroom,
    LessonOption,
    SundaySummary,
} from '@/types';

type Meeting = ClassMeeting & {
    is_today: boolean;
    is_future: boolean;
    can_take_attendance: boolean;
    position: { index: number; total: number } | null;
    previous_id: number | null;
    next_id: number | null;
};

type Props = {
    classroom: Classroom;
    today: string;
    meeting: Meeting;
    summary: SundaySummary | null;
    attendance: {
        roster: Roster;
        present: number[];
        visitors: number;
    } | null;
    lessons: LessonOption[];
};

/**
 * Um domingo da classe: a lição do dia, a chamada (pode ser feita ou corrigida
 * depois do dia), onde a turma parou e as ações do domingo.
 */
export default function MeetingPage({
    classroom,
    meeting,
    summary,
    attendance,
    lessons,
}: Props) {
    const confirm = useConfirm();
    const [cancelOpen, setCancelOpen] = useState(false);
    const [editOpen, setEditOpen] = useState(false);
    const cancelled = meeting.status === 'cancelled';
    const past = !meeting.is_future;
    const title = longDate(meeting.held_on);
    const here = show.url({ classroom: classroom.slug, meeting: meeting.id });

    const post = (url: string) =>
        router.post(url, {}, { preserveScroll: true });

    const remove = async () => {
        if (
            await confirm({
                title: 'Excluir este domingo?',
                description: `${title}. Use "Sem EBD" se não houve aula; excluir é para domingos criados por engano.`,
                confirmLabel: 'Excluir',
                destructive: true,
            })
        ) {
            router.delete(destroy.url(meeting.id));
        }
    };

    return (
        <>
            <Head title={`${dayMonth(meeting.held_on)} · ${classroom.name}`} />

            <Page>
                <ClassroomHeader
                    classroom={classroom}
                    active="domingos"
                    crumbs={[{ title: dayMonth(meeting.held_on) }]}
                />
                <PageHeader
                    title={title.charAt(0).toUpperCase() + title.slice(1)}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <MeetingStatusBadge
                                status={meeting.status}
                                label={meeting.status_label}
                            />
                            {meeting.is_today && (
                                <span className="font-medium text-primary">
                                    Hoje
                                </span>
                            )}
                            {meeting.position && meeting.position.total > 1 && (
                                <span>
                                    domingo {meeting.position.index} de{' '}
                                    {meeting.position.total} da lição
                                </span>
                            )}
                            {meeting.title && !cancelled && (
                                <span>{meeting.title}</span>
                            )}
                        </span>
                    }
                    actions={
                        <div className="flex gap-1">
                            <NeighborLink
                                classroom={classroom}
                                id={meeting.previous_id}
                                direction="previous"
                            />
                            <NeighborLink
                                classroom={classroom}
                                id={meeting.next_id}
                                direction="next"
                            />
                        </div>
                    }
                />

                <div className="space-y-10">
                    {!cancelled && (
                        <Section title="Lição do dia" icon={<BookOpen />}>
                            <LessonPicker
                                key={meeting.lesson_id ?? 'none'}
                                meeting={meeting}
                                lessons={lessons}
                            />
                            {meeting.lesson && (
                                <div className="mt-3 flex flex-wrap gap-2">
                                    <Button asChild size="sm" variant="outline">
                                        <Link
                                            href={sunday.url(
                                                meeting.lesson.slug,
                                                { query: { voltar: here } },
                                            )}
                                        >
                                            <Presentation /> Modo Domingo
                                        </Link>
                                    </Button>
                                    <Button asChild size="sm" variant="ghost">
                                        <Link
                                            href={editLesson(meeting.lesson.id)}
                                        >
                                            <ExternalLink /> Editar lição
                                        </Link>
                                    </Button>
                                </div>
                            )}
                        </Section>
                    )}

                    <Section
                        title="Chamada"
                        icon={<Users />}
                        description={
                            attendance
                                ? 'Toque no nome de quem estava. Salva sozinha e pode ser corrigida depois.'
                                : undefined
                        }
                    >
                        {cancelled ? (
                            <div className="rounded-2xl border border-dashed p-4 text-sm">
                                <p className="text-muted-foreground">
                                    Não teve EBD neste domingo
                                    {meeting.title ? ` (${meeting.title})` : ''}
                                    .
                                </p>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="mt-3"
                                    onClick={() =>
                                        post(restore.url(meeting.id))
                                    }
                                >
                                    <RotateCcw /> Voltar a ter EBD
                                </Button>
                            </div>
                        ) : attendance ? (
                            <>
                                {summary && (
                                    <p className="mb-4 flex items-center gap-2 rounded-xl bg-success-soft px-3 py-2 text-sm text-success-foreground">
                                        <CalendarCheck className="size-4 shrink-0" />
                                        {summary.present} de {summary.expected}{' '}
                                        presentes
                                        {summary.rate !== null &&
                                            ` (${summary.rate}%)`}
                                        {summary.visitors > 0 &&
                                            ` · ${summary.visitors} visitante${summary.visitors > 1 ? 's' : ''}`}
                                    </p>
                                )}
                                <AttendanceSheet
                                    key={meeting.id}
                                    meetingId={meeting.id}
                                    roster={attendance.roster}
                                    initialPresent={attendance.present}
                                    initialVisitors={attendance.visitors}
                                    reloadOnly={['summary', 'meeting']}
                                />
                            </>
                        ) : (
                            <p className="flex items-center gap-2 rounded-2xl border border-dashed p-4 text-sm text-muted-foreground">
                                <CalendarClock className="size-4 shrink-0" />A
                                chamada abre no dia do domingo.
                            </p>
                        )}
                    </Section>

                    {!cancelled && (
                        <Section
                            title="Onde paramos"
                            icon={<StickyNote />}
                            description="Só professores veem. Ajuda a preparar o próximo domingo."
                        >
                            <NotesForm
                                key={meeting.notes ?? ''}
                                meeting={meeting}
                            />
                        </Section>
                    )}

                    <Section title="Outras ações">
                        <div className="flex flex-wrap gap-2">
                            {!cancelled && past && meeting.lesson_id && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        post(continueMeeting.url(meeting.id))
                                    }
                                >
                                    <ChevronsRight /> A lição continua no
                                    próximo
                                </Button>
                            )}
                            {meeting.status === 'planned' &&
                                past &&
                                !meeting.has_attendance && (
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            post(held.url(meeting.id))
                                        }
                                    >
                                        <CheckCircle2 /> Teve aula, sem chamada
                                    </Button>
                                )}
                            {!cancelled && !meeting.has_attendance && (
                                <Button
                                    variant="outline"
                                    onClick={() => setCancelOpen(true)}
                                >
                                    <CalendarOff /> Sem EBD
                                </Button>
                            )}
                            <Button
                                variant="ghost"
                                onClick={() => setEditOpen(true)}
                            >
                                <Pencil /> Editar data e título
                            </Button>
                            {!meeting.has_attendance && (
                                <Button
                                    variant="ghost"
                                    className="text-destructive hover:text-destructive"
                                    onClick={remove}
                                >
                                    <Trash2 /> Excluir
                                </Button>
                            )}
                        </div>
                    </Section>
                </div>

                <CancelMeetingDialog
                    meeting={meeting}
                    dateLabel={title}
                    open={cancelOpen}
                    onOpenChange={setCancelOpen}
                />
                <EditMeetingDialog
                    key={`${meeting.held_on}-${meeting.title}`}
                    meeting={meeting}
                    open={editOpen}
                    onOpenChange={setEditOpen}
                />
            </Page>
        </>
    );
}

function NeighborLink({
    classroom,
    id,
    direction,
}: {
    classroom: Classroom;
    id: number | null;
    direction: 'previous' | 'next';
}) {
    const label =
        direction === 'previous' ? 'Domingo anterior' : 'Próximo domingo';
    const Icon = direction === 'previous' ? ChevronLeft : ChevronRight;

    if (id === null) {
        return (
            <Button variant="outline" size="icon" disabled aria-label={label}>
                <Icon />
            </Button>
        );
    }

    return (
        <Button asChild variant="outline" size="icon">
            <Link
                href={show({ classroom: classroom.slug, meeting: id })}
                aria-label={label}
            >
                <Icon />
            </Link>
        </Button>
    );
}

/**
 * Troca a lição do dia com um "Salvar" explícito (a agenda antiga salvava ao
 * tocar no seletor, sem como desfazer).
 */
function LessonPicker({
    meeting,
    lessons,
}: {
    meeting: Meeting;
    lessons: LessonOption[];
}) {
    const form = useForm<{ held_on: string; lesson_id: number | '' }>({
        held_on: meeting.held_on,
        lesson_id: meeting.lesson_id ?? '',
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((values) => ({
                    ...values,
                    lesson_id: values.lesson_id || null,
                }));
                form.put(update.url(meeting.id), { preserveScroll: true });
            }}
            className="flex flex-col gap-2 sm:flex-row"
        >
            <label htmlFor="meeting-lesson" className="sr-only">
                Lição do dia
            </label>
            <NativeSelect
                id="meeting-lesson"
                value={form.data.lesson_id}
                onChange={(event) =>
                    form.setData(
                        'lesson_id',
                        event.target.value ? Number(event.target.value) : '',
                    )
                }
                className="sm:flex-1"
            >
                <option value="">Lição a definir</option>
                {lessons.map((lesson) => (
                    <option key={lesson.id} value={lesson.id}>
                        {lesson.label}
                        {lesson.status === 'draft' ? ' (rascunho)' : ''}
                    </option>
                ))}
            </NativeSelect>
            {form.isDirty && (
                <Button type="submit" disabled={form.processing}>
                    Salvar lição
                </Button>
            )}
        </form>
    );
}

function NotesForm({ meeting }: { meeting: Meeting }) {
    const form = useForm({
        held_on: meeting.held_on,
        notes: meeting.notes ?? '',
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(update.url(meeting.id), { preserveScroll: true });
            }}
            className="space-y-2"
        >
            <Textarea
                value={form.data.notes}
                onChange={(event) => form.setData('notes', event.target.value)}
                rows={3}
                placeholder="Ex.: paramos no tópico 3; retomar a pergunta sobre Pedro."
                aria-label="Onde paramos"
                maxLength={5000}
            />
            <div className="flex justify-end">
                <Button
                    type="submit"
                    size="sm"
                    disabled={!form.isDirty || form.processing}
                >
                    Salvar anotação
                </Button>
            </div>
        </form>
    );
}
