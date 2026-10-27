import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BookOpen,
    CalendarClock,
    CalendarOff,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    Pencil,
    Presentation,
    RotateCcw,
    StickyNote,
} from 'lucide-react';
import { useState } from 'react';
import { CancelMeetingDialog } from '@/components/admin/meetings/cancel-meeting-dialog';
import { EditMeetingDialog } from '@/components/admin/meetings/edit-meeting-dialog';
import { MeetingStatusBadge } from '@/components/admin/meeting-status-badge';
import { AutosaveStatus } from '@/components/autosave-status';
import type { Roster } from '@/components/lesson/attendance-sheet';
import { AttendanceSheet } from '@/components/lesson/attendance-sheet';
import { Page, PageHeader, Section } from '@/components/page';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useAutosave } from '@/hooks/use-autosave';
import { dayMonth, longDate } from '@/lib/dates';
import { meetingWords } from '@/lib/meeting-words';
import { show as classroomPage } from '@/routes/admin/classrooms';
import { show } from '@/routes/admin/classrooms/meetings';
import { edit as editLesson } from '@/routes/admin/lessons';
import { held, restore, update } from '@/routes/admin/meetings';
import { update as updateNotes } from '@/routes/admin/meetings/notes';
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
 * Um domingo da classe. No dia (ou depois), abre direto na chamada; antes,
 * na escolha da lição. "Onde paramos" fica recolhido e as ações raras
 * (trocar a lição, repetir, editar a data, sem EBD) ficam em "Mais".
 */
export default function MeetingPage({
    classroom,
    meeting,
    attendance,
    lessons,
}: Props) {
    const [cancelOpen, setCancelOpen] = useState(false);
    const [editOpen, setEditOpen] = useState(false);
    const cancelled = meeting.status === 'cancelled';
    // Antes do dia (ou sem lição), escolher a lição é o que falta: aparece aberto.
    const [pickerOpen, setPickerOpen] = useState(
        !attendance || !meeting.lesson_id,
    );
    const words = meetingWords(classroom);
    const past = !meeting.is_future;
    const date = longDate(meeting.held_on);
    const here = show.url({ classroom: classroom.slug, meeting: meeting.id });
    const lessonTitle = meeting.lesson?.display_title ?? 'Sem lição';

    const post = (url: string) =>
        router.post(url, {}, { preserveScroll: true });

    return (
        <>
            <Head title={`${dayMonth(meeting.held_on)} · ${classroom.name}`} />

            <Page width="wide">
                <div className="max-w-3xl">
                    <Link
                        href={classroomPage.url(classroom.slug)}
                        className="inline-flex min-h-10 items-center gap-1 text-sm font-medium text-primary hover:underline print:hidden"
                    >
                        <ChevronLeft className="size-4" /> Agenda de{' '}
                        {classroom.name}
                    </Link>
                    <PageHeader
                        eyebrow={
                            <span className="flex flex-wrap items-center gap-2">
                                {meeting.is_today && 'Hoje · '}
                                {date.charAt(0).toUpperCase() + date.slice(1)}
                                {meeting.position &&
                                    meeting.position.index > 1 &&
                                    ` · ${meeting.position.index}º ${words.one} da lição`}
                                {cancelled && (
                                    <MeetingStatusBadge
                                        status={meeting.status}
                                        label={meeting.status_label}
                                    />
                                )}
                            </span>
                        }
                        title={
                            cancelled
                                ? `Sem ${words.program}${meeting.title ? ` · ${meeting.title}` : ''}`
                                : attendance
                                  ? `Chamada · ${lessonTitle}`
                                  : lessonTitle
                        }
                        actions={
                            <>
                                {meeting.lesson && !cancelled && (
                                    <Button asChild>
                                        <Link
                                            href={sunday.url(
                                                meeting.lesson.slug,
                                                { query: { voltar: here } },
                                            )}
                                        >
                                            <Presentation /> Apresentar lição
                                        </Link>
                                    </Button>
                                )}
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline">
                                            Mais <ChevronDown />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        {!cancelled && (
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    setPickerOpen(true)
                                                }
                                            >
                                                <BookOpen /> Trocar a lição do
                                                dia
                                            </DropdownMenuItem>
                                        )}
                                        {meeting.lesson && (
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href={editLesson(
                                                        meeting.lesson.id,
                                                    )}
                                                >
                                                    <ExternalLink /> Editar
                                                    lição
                                                </Link>
                                            </DropdownMenuItem>
                                        )}
                                        {meeting.status === 'planned' &&
                                            past &&
                                            !meeting.has_attendance && (
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        post(
                                                            held.url(
                                                                meeting.id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <CheckCircle2 /> Teve aula,
                                                    sem chamada
                                                </DropdownMenuItem>
                                            )}
                                        <DropdownMenuItem
                                            onSelect={() => setEditOpen(true)}
                                        >
                                            <Pencil /> Editar data e título
                                        </DropdownMenuItem>
                                        {!cancelled &&
                                            !meeting.has_attendance && (
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    onSelect={() =>
                                                        setCancelOpen(true)
                                                    }
                                                >
                                                    <CalendarOff /> Sem{' '}
                                                    {words.program} neste dia
                                                </DropdownMenuItem>
                                            )}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </>
                        }
                    />

                    <div className="space-y-8">
                        {!cancelled && pickerOpen && (
                            <Section title="Lição do dia" icon={<BookOpen />}>
                                <LessonPicker
                                    key={meeting.lesson_id ?? 'none'}
                                    meeting={meeting}
                                    lessons={lessons}
                                />
                            </Section>
                        )}

                        {cancelled ? (
                            <div className="rounded-2xl border border-dashed p-4 text-sm">
                                <p className="text-muted-foreground">
                                    Não teve {words.program} neste {words.one}
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
                                    <RotateCcw /> Voltar a ter {words.program}
                                </Button>
                            </div>
                        ) : attendance ? (
                            <AttendanceSheet
                                key={meeting.id}
                                meetingId={meeting.id}
                                roster={attendance.roster}
                                initialPresent={attendance.present}
                                initialVisitors={attendance.visitors}
                                reloadOnly={['summary', 'meeting']}
                                classroom={classroom}
                            />
                        ) : (
                            <p className="flex items-center gap-2 rounded-2xl border border-dashed p-4 text-sm text-muted-foreground">
                                <CalendarClock className="size-4 shrink-0" />A
                                chamada abre no dia do {words.one}.
                            </p>
                        )}

                        {!cancelled && (
                            <details
                                className="group border-t pt-4"
                                open={Boolean(meeting.notes)}
                            >
                                <summary className="flex min-h-10 cursor-pointer items-center gap-2 text-sm font-medium marker:content-none [&::-webkit-details-marker]:hidden">
                                    <ChevronRight className="size-4 text-muted-foreground transition-transform group-open:rotate-90" />
                                    <StickyNote className="size-4 text-muted-foreground" />
                                    Onde paramos
                                    <span className="font-normal text-muted-foreground">
                                        · só professores veem
                                    </span>
                                </summary>
                                <div className="mt-3">
                                    {/* Pelo id, não pelo texto: o salvamento automático
                                        recarrega o domingo e não pode remontar o campo. */}
                                    <NotesForm
                                        key={meeting.id}
                                        meeting={meeting}
                                    />
                                </div>
                            </details>
                        )}
                    </div>

                    <CancelMeetingDialog
                        classroom={classroom}
                        meeting={meeting}
                        dateLabel={date}
                        open={cancelOpen}
                        onOpenChange={setCancelOpen}
                    />
                    <EditMeetingDialog
                        key={`${meeting.held_on}-${meeting.title}`}
                        classroom={classroom}
                        meeting={meeting}
                        open={editOpen}
                        onOpenChange={setEditOpen}
                    />
                </div>
            </Page>
        </>
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
            noValidate
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
                {groupBySeries(lessons, meeting.lesson_id).map((group) => (
                    <optgroup key={group.key} label={group.title}>
                        {group.lessons.map((lesson) => (
                            <option key={lesson.id} value={lesson.id}>
                                {lesson.label}
                                {lesson.status === 'draft' ? ' (rascunho)' : ''}
                            </option>
                        ))}
                    </optgroup>
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
    const initial = meeting.notes ?? '';
    const [notes, setNotes] = useState(initial);
    const { status, retry } = useAutosave(notes, initial, (value, callbacks) =>
        router.put(
            updateNotes.url(meeting.id),
            { notes: value },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['meeting'],
                ...callbacks,
            },
        ),
    );

    return (
        <div className="space-y-2">
            <Textarea
                value={notes}
                onChange={(event) => setNotes(event.target.value)}
                rows={3}
                placeholder="Ex.: paramos no tópico 3; retomar a pergunta sobre Pedro."
                aria-label="Onde paramos"
                aria-describedby="meeting-notes-status"
                maxLength={5000}
            />
            <p
                id="meeting-notes-status"
                className="min-h-4 text-xs text-muted-foreground"
                aria-live="polite"
            >
                <AutosaveStatus status={status} onRetry={retry} />
            </p>
        </div>
    );
}

type LessonGroup = { key: string; title: string; lessons: LessonOption[] };

/**
 * Lições por série: a série da lição escolhida primeiro, as outras em ordem
 * alfabética e as avulsas no fim. Sem grupos, "Lição 1" de séries diferentes
 * ficavam lado a lado.
 */
function groupBySeries(
    lessons: LessonOption[],
    currentId: number | null,
): LessonGroup[] {
    const groups = new Map<string, LessonGroup>();
    const keyOf = (lesson: LessonOption) =>
        String(lesson.series_id ?? 'avulsas');

    for (const lesson of lessons) {
        const key = keyOf(lesson);
        const group = groups.get(key) ?? {
            key,
            title: lesson.series_title ?? 'Lições avulsas',
            lessons: [],
        };
        group.lessons.push(lesson);
        groups.set(key, group);
    }

    // A lição escolhida pode ser avulsa: aí o grupo das avulsas sobe também.
    const current = lessons.find((lesson) => lesson.id === currentId);
    const currentKey = current ? keyOf(current) : null;

    return [...groups.values()].sort((a, b) => {
        const rank = (g: LessonGroup) =>
            g.key === currentKey ? 0 : g.key === 'avulsas' ? 2 : 1;

        return rank(a) - rank(b) || a.title.localeCompare(b.title, 'pt-BR');
    });
}
