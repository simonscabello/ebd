import { Head, Link, router } from '@inertiajs/react';
import { BookOpen, Check, MessageSquareText, Pencil } from 'lucide-react';
import type { ReactNode } from 'react';
import { ClassroomShell } from '@/components/admin/classroom-header';
import type { ClassroomHeaderData } from '@/components/admin/classroom-header';
import {
    LessonMenu,
    LessonNumber,
    LessonStatusBadge,
} from '@/components/admin/lesson-row';
import { StatusBadge } from '@/components/admin/status-badge';
import { CopyWhatsAppButtons } from '@/components/copy-whatsapp-button';
import { Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { WhatsAppButton } from '@/components/whatsapp-button';
import { dayMonth, relativeDay } from '@/lib/dates';
import { meetingWords } from '@/lib/meeting-words';
import { missYouMessage } from '@/lib/whatsapp';
import { lessons } from '@/routes/admin/classrooms';
import { advance } from '@/routes/admin/classrooms/current-lesson';
import { show as meetingPage } from '@/routes/admin/classrooms/meetings';
import { show as studentPage } from '@/routes/admin/classrooms/students';
import { edit as editLesson } from '@/routes/admin/lessons';
import { show } from '@/routes/lessons';
import type { Classroom, LessonStatus } from '@/types';

type LessonRow = {
    id: number;
    slug: string;
    number: number | null;
    display_title: string;
    status: LessonStatus;
};

type Current = LessonRow & {
    bible_reference: string | null;
    series: { id: number; title: string } | null;
    started_on: string | null;
    meetings_held: number;
};

/** Só o que a Visão geral usa do resumo da classe (ClassroomOverviewQuery). */
type Overview = {
    today: string;
    week: {
        meeting: { id: number; held_on: string; is_today: boolean };
        weekly_message: string | null;
    } | null;
    home_study: { readers: number; expected: number } | null;
    attention: {
        id: number;
        name: string;
        phone: string | null;
        reasons: string[];
        missed_in_a_row: number;
    }[];
    birthdays: {
        id: number;
        name: string;
        day: number;
        is_today: boolean;
    }[];
    recent: {
        id: number;
        held_on: string;
        lesson: string | null;
        title: string | null;
        present: number;
        expected: number;
        visitors: number;
    }[];
    pending: { id: number; held_on: string; lesson: string | null }[];
};

type Props = {
    classroom: Classroom;
    header: ClassroomHeaderData;
    overview: Overview;
    current: Current | null;
    upNext: LessonRow[];
};

/**
 * Página da classe (Visão geral). A lição atual no topo: ela fica quantos
 * domingos precisar e só sai quando o professor conclui. Embaixo, as próximas
 * da série em sequência, sem datas. Ao lado, quem precisa de atenção e a
 * última chamada.
 */
export default function ClassroomOverview({
    classroom,
    header,
    overview,
    current,
    upNext,
}: Props) {
    const words = meetingWords(classroom);
    const lastAttendance = overview.recent[0] ?? null;

    return (
        <>
            <Head title={classroom.name} />

            <Page width="wide">
                <ClassroomShell
                    classroom={classroom}
                    header={header}
                    active="geral"
                />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="space-y-6">
                        {current ? (
                            <CurrentLessonCard
                                classroom={classroom}
                                current={current}
                                next={upNext[0] ?? null}
                                overview={overview}
                                canManage={header.can_manage}
                            />
                        ) : (
                            <section className="rounded-2xl border border-dashed p-6">
                                <h2 className="font-serif text-xl font-semibold">
                                    Qual lição a classe estuda agora?
                                </h2>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Escolha na aba Lições: ela fica quantos{' '}
                                    {words.many} precisar, até você concluir.
                                </p>
                                {header.can_manage && (
                                    <Button asChild className="mt-4">
                                        <Link
                                            href={lessons.url(classroom.slug)}
                                        >
                                            <BookOpen /> Escolher a lição
                                        </Link>
                                    </Button>
                                )}
                            </section>
                        )}

                        {upNext.length > 0 && (
                            <Card
                                title="A seguir na série"
                                action={
                                    header.can_manage && (
                                        <Link
                                            href={lessons.url(classroom.slug)}
                                            className="text-sm text-primary hover:underline"
                                        >
                                            Ver a série inteira
                                        </Link>
                                    )
                                }
                            >
                                <ul className="divide-y">
                                    {upNext.map((lesson) => (
                                        <li
                                            key={lesson.id}
                                            className="flex items-center gap-3 px-4 py-3"
                                        >
                                            <LessonNumber
                                                number={lesson.number}
                                            />
                                            <span className="min-w-0 flex-1 truncate">
                                                {lesson.display_title}
                                            </span>
                                            <LessonStatusBadge
                                                status={lesson.status}
                                            />
                                            {header.can_manage && (
                                                <LessonMenu
                                                    classroom={classroom}
                                                    lesson={lesson}
                                                />
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </Card>
                        )}
                    </div>

                    <div className="space-y-6">
                        {overview.pending.length > 0 && header.can_manage && (
                            <Card title="Chamada pendente">
                                <ul className="divide-y">
                                    {overview.pending.map((meeting) => (
                                        <li
                                            key={meeting.id}
                                            className="flex items-center gap-3 px-4 py-3 text-sm"
                                        >
                                            <span className="flex-1 first-letter:uppercase">
                                                {dayMonth(meeting.held_on)}
                                            </span>
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    href={meetingPage.url([
                                                        classroom.slug,
                                                        meeting.id,
                                                    ])}
                                                >
                                                    Fazer chamada
                                                </Link>
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            </Card>
                        )}

                        <Card
                            title="Precisam de atenção"
                            action={
                                overview.attention.length > 0 && (
                                    <span className="rounded-md bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive">
                                        {overview.attention.length}
                                    </span>
                                )
                            }
                        >
                            {overview.attention.length === 0 &&
                            overview.birthdays.length === 0 ? (
                                <p className="px-4 py-3 text-sm text-muted-foreground">
                                    Ninguém por enquanto.
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {overview.attention.map((student) => (
                                        <li
                                            key={student.id}
                                            className="flex items-center gap-3 px-4 py-3"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <Link
                                                    href={studentPage.url([
                                                        classroom.slug,
                                                        student.id,
                                                    ])}
                                                    className="block truncate font-medium hover:underline"
                                                >
                                                    {student.name}
                                                </Link>
                                                <span className="block text-xs text-muted-foreground">
                                                    {student.missed_in_a_row > 1
                                                        ? `faltou os ${student.missed_in_a_row} últimos`
                                                        : student.reasons[0]}
                                                </span>
                                            </div>
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
                                    {overview.birthdays.map((student) => (
                                        <li
                                            key={`b${student.id}`}
                                            className="flex items-center gap-3 px-4 py-3"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <Link
                                                    href={studentPage.url([
                                                        classroom.slug,
                                                        student.id,
                                                    ])}
                                                    className="block truncate font-medium hover:underline"
                                                >
                                                    {student.name}
                                                </Link>
                                                <span className="block text-xs text-muted-foreground">
                                                    {student.is_today
                                                        ? 'faz aniversário hoje'
                                                        : `faz aniversário dia ${student.day}`}
                                                </span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>

                        {lastAttendance && (
                            <Card
                                title="Última chamada"
                                action={
                                    header.can_manage && (
                                        <Link
                                            href={meetingPage.url([
                                                classroom.slug,
                                                lastAttendance.id,
                                            ])}
                                            className="text-sm text-primary hover:underline"
                                        >
                                            Abrir
                                        </Link>
                                    )
                                }
                            >
                                <div className="px-4 py-3 text-sm">
                                    <div className="flex items-baseline justify-between gap-3">
                                        <span className="first-letter:uppercase">
                                            {dayMonth(lastAttendance.held_on)}
                                        </span>
                                        <span className="font-semibold">
                                            {lastAttendance.present} de{' '}
                                            {lastAttendance.expected}
                                        </span>
                                    </div>
                                    <p className="mt-1 truncate text-xs text-muted-foreground">
                                        {lastAttendance.lesson ??
                                            lastAttendance.title}
                                        {lastAttendance.visitors > 0 &&
                                            ` · ${lastAttendance.visitors} ${lastAttendance.visitors === 1 ? 'visitante' : 'visitantes'}`}
                                    </p>
                                </div>
                            </Card>
                        )}
                    </div>
                </div>
            </Page>
        </>
    );
}

/**
 * A lição atual, em destaque: de que série, desde quando, quantos já leram e
 * as ações (apresentar, mandar a mensagem, concluir e passar).
 */
function CurrentLessonCard({
    classroom,
    current,
    next,
    overview,
    canManage,
}: {
    classroom: Classroom;
    current: Current;
    next: LessonRow | null;
    overview: Overview;
    canManage: boolean;
}) {
    const words = meetingWords(classroom);
    const meeting = overview.week?.meeting ?? null;
    const readers = overview.home_study;
    const draft = current.status === 'draft';
    const since = current.started_on
        ? `em estudo desde ${dayMonth(current.started_on)}`
        : null;
    const held =
        current.meetings_held > 1
            ? `${current.meetings_held} ${words.many}`
            : null;

    return (
        <section className="rounded-2xl border border-primary/40 bg-card p-5 sm:p-6">
            <div className="flex flex-wrap items-center gap-2 text-xs">
                <span className="rounded-md bg-primary/15 px-2 py-0.5 font-medium text-primary">
                    Lição atual
                </span>
                {current.series && (
                    <span className="text-muted-foreground">
                        {current.series.title}
                    </span>
                )}
                {draft && <StatusBadge status="draft" label="Rascunho" />}
            </div>
            <h2 className="mt-3 font-serif text-2xl font-semibold tracking-tight sm:text-3xl">
                {current.display_title}
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
                {[current.bible_reference, since, held]
                    .filter(Boolean)
                    .join(' · ')}
            </p>

            <div className="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground">
                {meeting && (
                    <span>
                        Próximo {words.one}:{' '}
                        <span className="text-foreground">
                            {meeting.is_today
                                ? 'hoje'
                                : relativeDay(meeting.held_on, overview.today)}
                        </span>
                    </span>
                )}
                {readers && readers.expected > 0 && (
                    <span className="flex items-center gap-2">
                        <span
                            aria-hidden="true"
                            className="h-1.5 w-24 overflow-hidden rounded-full bg-muted"
                        >
                            <span
                                className="block h-full rounded-full bg-primary"
                                style={{
                                    width: `${Math.round((readers.readers / readers.expected) * 100)}%`,
                                }}
                            />
                        </span>
                        {readers.readers} de {readers.expected} já leram o
                        estudo
                    </span>
                )}
            </div>

            <div className="mt-5 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                {draft ? (
                    canManage && (
                        <Button asChild>
                            <Link href={editLesson.url(current.id)}>
                                <Pencil /> Terminar lição
                            </Link>
                        </Button>
                    )
                ) : (
                    <Button asChild>
                        <Link href={show.url(current.slug)}>
                            <BookOpen /> Ver lição
                        </Link>
                    </Button>
                )}
                {overview.week?.weekly_message && (
                    <MessageDialog
                        title={current.display_title}
                        text={overview.week.weekly_message}
                    />
                )}
                {canManage && !draft && (
                    <Button asChild variant="outline">
                        <Link href={editLesson.url(current.id)}>
                            <Pencil /> Editar
                        </Link>
                    </Button>
                )}
                {canManage && (
                    <AdvanceDialog
                        classroom={classroom}
                        current={current}
                        next={next}
                    />
                )}
            </div>
        </section>
    );
}

/** "Concluir e passar": confirma antes, porque a lição sai do destaque. */
function AdvanceDialog({
    classroom,
    current,
    next,
}: {
    classroom: Classroom;
    current: Current;
    next: LessonRow | null;
}) {
    const label = next
        ? `Concluir e passar para a ${next.number ?? 'próxima'}`
        : 'Concluir a lição';

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" className="sm:ml-auto">
                    <Check /> {label}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Concluir “{current.display_title}”?
                    </DialogTitle>
                    <DialogDescription>
                        {next
                            ? `A lição vira estudada e a classe passa para “${next.display_title}”.`
                            : 'A lição vira estudada. A série não tem próxima lição: escreva a próxima ou conclua a série na aba Lições.'}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Voltar</Button>
                    </DialogClose>
                    <Button
                        onClick={() =>
                            router.post(
                                advance.url(classroom.slug),
                                { lesson_id: current.id },
                                { preserveScroll: true },
                            )
                        }
                    >
                        <Check /> Concluir
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function Card({
    title,
    action,
    children,
}: {
    title: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="overflow-hidden rounded-2xl border bg-card">
            <div className="flex items-center gap-3 border-b px-4 py-3">
                <h2 className="flex-1 text-sm font-semibold">{title}</h2>
                {action}
            </div>
            {children}
        </section>
    );
}

/** Mensagem pronta da lição para o grupo da classe. */
function MessageDialog({ title, text }: { title: string; text: string }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <MessageSquareText /> Mensagem do grupo
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
