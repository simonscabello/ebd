import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CalendarOff,
    CalendarPlus,
    CheckCircle2,
    ChevronsRight,
    ExternalLink,
    MessageSquareText,
    StickyNote,
    Trash2,
    Wand2,
} from 'lucide-react';
import { useState } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import { CopyWhatsAppButtons } from '@/components/copy-whatsapp-button';
import { Field } from '@/components/form-field';
import { EmptyState, Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import { plan, store } from '@/routes/admin/classrooms/meetings';
import { edit as editLesson } from '@/routes/admin/lessons';
import {
    cancel,
    continueMethod as continueMeeting,
    destroy,
    held,
    update,
} from '@/routes/admin/meetings';
import type { ClassMeeting, Classroom, Series } from '@/types';

type LessonOption = {
    id: number;
    label: string;
    status: 'draft' | 'published';
    series_id: number | null;
};

type Props = {
    classroom: Classroom;
    meetings: ClassMeeting[];
    lessons: LessonOption[];
    series: Series[];
    today: string;
    nextSunday: string;
    weeklyMessage: string | null;
};

const monthFormatter = new Intl.DateTimeFormat('pt-BR', {
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
});

/**
 * Agenda da classe: cada domingo, a lição do dia, domingos sem EBD e
 * lições que continuam no domingo seguinte.
 */
export default function ClassroomAgenda({
    classroom,
    meetings,
    lessons,
    series,
    today,
    nextSunday,
    weeklyMessage,
}: Props) {
    const months = groupByMonth(meetings);

    return (
        <>
            <Head title={`Agenda · ${classroom.name}`} />

            <Page width="wide">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Gestão', href: dashboard.url() },
                        { title: classroom.name },
                        { title: 'Agenda' },
                    ]}
                    title="Agenda"
                    description="Os domingos da classe e a lição de cada um. Uma lição pode ocupar mais de um domingo."
                    actions={
                        <>
                            <PlanDialog
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

                {weeklyMessage && (
                    <section className="mb-8 rounded-2xl border bg-card p-4">
                        <h2 className="flex items-center gap-2 font-semibold">
                            <MessageSquareText className="size-5 text-primary" />{' '}
                            Mensagem da semana
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Pronta para o grupo da classe.
                        </p>
                        <pre className="mt-3 max-h-56 overflow-auto rounded-xl bg-muted/60 p-3 font-sans text-sm whitespace-pre-wrap">
                            {weeklyMessage}
                        </pre>
                        <div className="mt-3">
                            <CopyWhatsAppButtons
                                text={weeklyMessage}
                                copyLabel="Copiar mensagem"
                            />
                        </div>
                    </section>
                )}

                {meetings.length === 0 ? (
                    <EmptyState
                        icon={<CalendarPlus />}
                        title="Nenhum domingo na agenda"
                    >
                        Use “Planejar trimestre” para criar os domingos e
                        distribuir as lições da revista.
                    </EmptyState>
                ) : (
                    <div className="space-y-8">
                        {months.map(([month, items]) => (
                            <section key={month}>
                                <h2 className="mb-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                    {month}
                                </h2>
                                <ul className="divide-y rounded-2xl border bg-card">
                                    {items.map((meeting) => (
                                        <MeetingRow
                                            key={meeting.id}
                                            meeting={meeting}
                                            lessons={lessons}
                                            today={today}
                                        />
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}
            </Page>
        </>
    );
}

function MeetingRow({
    meeting,
    lessons,
    today,
}: {
    meeting: ClassMeeting;
    lessons: LessonOption[];
    today: string;
}) {
    const confirm = useConfirm();
    const [notesOpen, setNotesOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const past = meeting.held_on <= today;
    const cancelled = meeting.status === 'cancelled';
    const day = new Date(`${meeting.held_on}T00:00:00Z`);

    const save = (data: Partial<ClassMeeting>) =>
        router.put(
            update.url(meeting.id),
            {
                held_on: meeting.held_on,
                lesson_id: meeting.lesson_id,
                title: meeting.title,
                notes: meeting.notes ?? null,
                ...data,
            },
            { preserveScroll: true },
        );

    const remove = async () => {
        if (
            await confirm({
                title: 'Remover este domingo da agenda?',
                description: meeting.date_label,
                confirmLabel: 'Remover',
                destructive: true,
            })
        ) {
            router.delete(destroy.url(meeting.id), { preserveScroll: true });
        }
    };

    return (
        <li
            className={cn(
                'flex flex-col gap-3 p-4 sm:flex-row sm:items-start',
                cancelled && 'bg-muted/40',
            )}
        >
            <div className="flex w-full items-start gap-4 sm:w-auto">
                <div className="w-14 shrink-0 text-center">
                    <p className="text-xs text-muted-foreground uppercase">
                        {day.toLocaleDateString('pt-BR', {
                            weekday: 'short',
                            timeZone: 'UTC',
                        })}
                    </p>
                    <p
                        className={cn(
                            'font-serif text-2xl font-semibold',
                            cancelled && 'text-muted-foreground line-through',
                        )}
                    >
                        {day.getUTCDate()}
                    </p>
                </div>
                <span
                    className={cn(
                        'mt-1 shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium sm:hidden',
                        statusStyle(meeting.status),
                    )}
                >
                    {meeting.status_label}
                </span>
            </div>

            <div className="min-w-0 flex-1 space-y-2">
                {cancelled ? (
                    <p className="py-2 text-sm text-muted-foreground">
                        Sem EBD{meeting.title ? ` · ${meeting.title}` : ''}
                    </p>
                ) : (
                    <div className="flex items-center gap-2">
                        <label
                            htmlFor={`lesson-${meeting.id}`}
                            className="sr-only"
                        >
                            Lição do dia
                        </label>
                        <NativeSelect
                            id={`lesson-${meeting.id}`}
                            value={meeting.lesson_id ?? ''}
                            onChange={(event) =>
                                save({
                                    lesson_id: event.target.value
                                        ? Number(event.target.value)
                                        : null,
                                })
                            }
                            disabled={meeting.has_attendance}
                        >
                            <option value="">
                                {meeting.title ?? 'Sem lição definida'}
                            </option>
                            {lessons.map((lesson) => (
                                <option key={lesson.id} value={lesson.id}>
                                    {lesson.label}
                                    {lesson.status === 'draft'
                                        ? ' (rascunho)'
                                        : ''}
                                </option>
                            ))}
                        </NativeSelect>
                        {meeting.lesson && (
                            <Button asChild variant="ghost" size="icon">
                                <Link
                                    href={editLesson(meeting.lesson.id)}
                                    aria-label="Editar lição"
                                >
                                    <ExternalLink />
                                </Link>
                            </Button>
                        )}
                    </div>
                )}

                {meeting.notes && !notesOpen && (
                    <p className="text-sm text-muted-foreground">
                        <StickyNote className="mr-1 inline size-3.5" />
                        {meeting.notes}
                    </p>
                )}
                {notesOpen && (
                    <NotesEditor
                        meeting={meeting}
                        onSave={(notes) => {
                            save({ notes });
                            setNotesOpen(false);
                        }}
                        onCancel={() => setNotesOpen(false)}
                    />
                )}

                <div className="flex flex-wrap gap-1.5">
                    <span
                        className={cn(
                            'hidden rounded-full px-2.5 py-1 text-xs font-medium sm:inline-flex',
                            statusStyle(meeting.status),
                        )}
                    >
                        {meeting.status_label}
                        {meeting.has_attendance && ' · chamada feita'}
                    </span>
                    {!cancelled && past && meeting.status === 'planned' && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    held.url(meeting.id),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <CheckCircle2 /> Teve aula
                        </Button>
                    )}
                    {!cancelled && past && meeting.lesson_id && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    continueMeeting.url(meeting.id),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <ChevronsRight /> Continua no próximo
                        </Button>
                    )}
                    {!cancelled && !meeting.has_attendance && (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setCancelOpen(true)}
                        >
                            <CalendarOff /> Sem EBD
                        </Button>
                    )}
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => setNotesOpen(true)}
                    >
                        <StickyNote /> Anotação
                    </Button>
                    {!meeting.has_attendance && (
                        <Button
                            size="sm"
                            variant="ghost"
                            className="text-destructive hover:text-destructive"
                            onClick={remove}
                            aria-label="Remover domingo"
                        >
                            <Trash2 />
                        </Button>
                    )}
                </div>
            </div>

            <CancelMeetingDialog
                meeting={meeting}
                open={cancelOpen}
                onOpenChange={setCancelOpen}
            />
        </li>
    );
}

/**
 * "Sem EBD": motivo (opcional) e, se houver lição marcada, a opção de
 * empurrar a lição e as seguintes para o próximo domingo.
 */
function CancelMeetingDialog({
    meeting,
    open,
    onOpenChange,
}: {
    meeting: ClassMeeting;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({ reason: '', shift: meeting.lesson_id !== null });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(cancel.url(meeting.id), {
                            preserveScroll: true,
                            onSuccess: () => onOpenChange(false),
                        });
                    }}
                    className="space-y-4"
                >
                    <DialogHeader>
                        <DialogTitle>Domingo sem EBD</DialogTitle>
                        <DialogDescription className="first-letter:uppercase">
                            {meeting.date_label}. O aviso aparece para a classe
                            no Início.
                        </DialogDescription>
                    </DialogHeader>
                    <Field
                        label="Motivo (opcional)"
                        htmlFor={`cancel-reason-${meeting.id}`}
                        error={form.errors.reason}
                    >
                        <Input
                            id={`cancel-reason-${meeting.id}`}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            placeholder="Ex.: Culto de Missões"
                            maxLength={120}
                            autoFocus
                        />
                    </Field>
                    {meeting.lesson_id !== null && (
                        <label className="flex cursor-pointer items-start gap-3 rounded-xl border bg-card p-3.5 text-sm">
                            <Checkbox
                                checked={form.data.shift}
                                onCheckedChange={(value) =>
                                    form.setData('shift', value === true)
                                }
                                className="mt-0.5"
                            />
                            <span>
                                Empurrar esta lição (e as seguintes) para o
                                próximo domingo
                                <span className="block text-muted-foreground">
                                    Desmarque se a lição simplesmente não será
                                    dada.
                                </span>
                            </span>
                        </label>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => onOpenChange(false)}
                        >
                            Voltar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            <CalendarOff /> Marcar sem EBD
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function NotesEditor({
    meeting,
    onSave,
    onCancel,
}: {
    meeting: ClassMeeting;
    onSave: (notes: string) => void;
    onCancel: () => void;
}) {
    const [notes, setNotes] = useState(meeting.notes ?? '');

    return (
        <div className="space-y-2">
            <Textarea
                value={notes}
                onChange={(event) => setNotes(event.target.value)}
                rows={2}
                placeholder="Ex.: paramos no tópico II.2; retomar a pergunta 3."
                aria-label="Anotação do encontro"
            />
            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={onCancel}
                >
                    Cancelar
                </Button>
                <Button type="button" size="sm" onClick={() => onSave(notes)}>
                    Salvar anotação
                </Button>
            </div>
        </div>
    );
}

function PlanDialog({
    classroom,
    series,
    nextSunday,
}: {
    classroom: Classroom;
    series: Series[];
    nextSunday: string;
}) {
    const [open, setOpen] = useState(false);
    const current = series[0];
    const form = useForm<{ from: string; to: string; series_id: number | '' }>({
        from: current?.starts_on ?? nextSunday,
        to: current?.ends_on ?? '',
        series_id: current?.id ?? '',
    });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Wand2 /> Planejar trimestre
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((values) => ({
                            ...values,
                            series_id: values.series_id || null,
                        }));
                        form.submit(plan(classroom.slug), {
                            preserveScroll: true,
                            onSuccess: () => setOpen(false),
                        });
                    }}
                    className="space-y-4"
                >
                    <DialogHeader>
                        <DialogTitle>Planejar trimestre</DialogTitle>
                        <DialogDescription>
                            Cria um encontro para cada domingo do período e
                            distribui as lições da série (pela numeração da
                            revista) nos domingos livres. Nada que já está na
                            agenda é alterado.
                        </DialogDescription>
                    </DialogHeader>
                    <Field
                        label="Série / revista"
                        htmlFor="plan-series"
                        error={form.errors.series_id}
                    >
                        <NativeSelect
                            id="plan-series"
                            value={form.data.series_id}
                            onChange={(event) => {
                                const chosen = series.find(
                                    (s) => s.id === Number(event.target.value),
                                );
                                form.setData((data) => ({
                                    ...data,
                                    series_id: chosen?.id ?? '',
                                    from: chosen?.starts_on ?? data.from,
                                    to: chosen?.ends_on ?? data.to,
                                }));
                            }}
                        >
                            <option value="">Só criar os domingos</option>
                            {series.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.title}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field
                            label="De"
                            htmlFor="plan-from"
                            error={form.errors.from}
                        >
                            <Input
                                id="plan-from"
                                type="date"
                                value={form.data.from}
                                onChange={(event) =>
                                    form.setData('from', event.target.value)
                                }
                                required
                            />
                        </Field>
                        <Field
                            label="Até"
                            htmlFor="plan-to"
                            error={form.errors.to}
                        >
                            <Input
                                id="plan-to"
                                type="date"
                                value={form.data.to}
                                onChange={(event) =>
                                    form.setData('to', event.target.value)
                                }
                                required
                            />
                        </Field>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Planejar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AddMeetingDialog({
    classroom,
    lessons,
    nextSunday,
}: {
    classroom: Classroom;
    lessons: LessonOption[];
    nextSunday: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<{
        held_on: string;
        lesson_id: number | '';
        title: string;
    }>({
        held_on: nextSunday,
        lesson_id: '',
        title: '',
    });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <CalendarPlus /> Adicionar encontro
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((values) => ({
                            ...values,
                            lesson_id: values.lesson_id || null,
                            title: values.title || null,
                        }));
                        form.submit(store(classroom.slug), {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset();
                                setOpen(false);
                            },
                        });
                    }}
                    className="space-y-4"
                >
                    <DialogHeader>
                        <DialogTitle>Adicionar encontro</DialogTitle>
                        <DialogDescription>
                            Um domingo (ou outro dia) de aula da classe.
                        </DialogDescription>
                    </DialogHeader>
                    <Field
                        label="Data"
                        htmlFor="meeting-date"
                        error={form.errors.held_on}
                    >
                        <Input
                            id="meeting-date"
                            type="date"
                            value={form.data.held_on}
                            onChange={(event) =>
                                form.setData('held_on', event.target.value)
                            }
                            required
                        />
                    </Field>
                    <Field
                        label="Lição"
                        htmlFor="meeting-lesson"
                        error={form.errors.lesson_id}
                    >
                        <NativeSelect
                            id="meeting-lesson"
                            value={form.data.lesson_id}
                            onChange={(event) =>
                                form.setData(
                                    'lesson_id',
                                    event.target.value
                                        ? Number(event.target.value)
                                        : '',
                                )
                            }
                        >
                            <option value="">A definir</option>
                            {lessons.map((lesson) => (
                                <option key={lesson.id} value={lesson.id}>
                                    {lesson.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    <Field
                        label="Título (opcional)"
                        htmlFor="meeting-title"
                        error={form.errors.title}
                        hint="Para encontros especiais, ex.: Revisão do trimestre."
                    >
                        <Input
                            id="meeting-title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            maxLength={120}
                        />
                    </Field>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Adicionar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function statusStyle(status: ClassMeeting['status']): string {
    return {
        planned: 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
        held: 'bg-muted text-muted-foreground',
        cancelled:
            'bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-200',
    }[status];
}

function groupByMonth(meetings: ClassMeeting[]): [string, ClassMeeting[]][] {
    const groups = new Map<string, ClassMeeting[]>();

    for (const meeting of meetings) {
        const label = monthFormatter.format(
            new Date(`${meeting.held_on}T00:00:00Z`),
        );
        groups.set(label, [...(groups.get(label) ?? []), meeting]);
    }

    return [...groups.entries()];
}
