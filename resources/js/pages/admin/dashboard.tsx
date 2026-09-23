import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CalendarDays,
    ChartLine,
    CalendarOff,
    CheckCircle2,
    Layers,
    Plus,
    Users,
} from 'lucide-react';
import { StatusBadge } from '@/components/admin/status-badge';
import { EmptyState, Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { insights } from '@/routes/admin/classrooms';
import { index as membersIndex } from '@/routes/admin/classrooms/members';
import { index as agendaIndex } from '@/routes/admin/classrooms/meetings';
import { cancel, held } from '@/routes/admin/meetings';
import {
    create as createLesson,
    edit as editLesson,
} from '@/routes/admin/lessons';
import { create as createSeries } from '@/routes/admin/series';
import type { ClassMeeting, Classroom, Lesson } from '@/types';

type MeetingToConfirm = ClassMeeting & {
    classroom: { name: string; slug: string };
};

type Props = {
    classrooms: Classroom[];
    upcoming: Lesson[];
    meetingsToConfirm: MeetingToConfirm[];
    isAdmin: boolean;
};

export default function AdminDashboard({
    classrooms,
    upcoming,
    meetingsToConfirm,
}: Props) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Gestão" />

            <Page width="wide">
                <PageHeader
                    eyebrow="Gestão da EBD"
                    title={`Olá, ${auth.user?.first_name ?? ''}`}
                    description={
                        classrooms.length
                            ? `Você cuida de: ${classrooms.map((c) => c.name).join(', ')}.`
                            : 'Nenhuma classe atribuída a você ainda.'
                    }
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={createSeries()}>
                                    <Layers /> Nova série
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={createLesson()}>
                                    <Plus /> Nova lição
                                </Link>
                            </Button>
                        </>
                    }
                />

                {meetingsToConfirm.length > 0 && (
                    <section className="mb-8 rounded-2xl border border-amber-300/60 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40">
                        <h2 className="flex items-center gap-2 font-semibold text-amber-900 dark:text-amber-200">
                            <AlertCircle className="size-5" /> Domingos para
                            confirmar
                        </h2>
                        <p className="mt-1 text-sm text-amber-900/80 dark:text-amber-200/80">
                            Teve aula nestes domingos? Confirme para manter a
                            agenda e o histórico da classe em dia.
                        </p>
                        <ul className="mt-3 space-y-2">
                            {meetingsToConfirm.map((meeting) => (
                                <li
                                    key={meeting.id}
                                    className="flex flex-col gap-2 rounded-xl bg-card px-3 py-2.5 text-sm sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <span>
                                        <span className="font-medium">
                                            {meeting.lesson?.display_title ??
                                                meeting.title ??
                                                'Encontro sem lição'}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {meeting.classroom.name} ·{' '}
                                            {meeting.date_short}
                                        </span>
                                    </span>
                                    <span className="flex gap-2">
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
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.post(
                                                    cancel.url(meeting.id),
                                                    { shift: false },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <CalendarOff /> Não teve
                                        </Button>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section>
                    <h2 className="mb-3 flex items-center gap-2 text-lg font-semibold">
                        <CalendarDays className="size-5 text-primary" />{' '}
                        Próximas aulas
                    </h2>
                    {upcoming.length === 0 ? (
                        <EmptyState
                            icon={<CalendarDays />}
                            title="Nenhuma aula planejada"
                        >
                            Crie a próxima lição para que os alunos possam se
                            preparar.
                        </EmptyState>
                    ) : (
                        <ul className="divide-y rounded-2xl border bg-card">
                            {upcoming.map((lesson) => (
                                <li key={lesson.id}>
                                    <Link
                                        href={editLesson(lesson.id)}
                                        className="flex items-center gap-4 px-4 py-3.5 hover:bg-muted/50"
                                    >
                                        <div className="w-16 shrink-0 text-center">
                                            <p className="text-xs text-muted-foreground uppercase">
                                                {lesson.date_parts?.month ??
                                                    'sem'}
                                            </p>
                                            <p className="font-serif text-2xl font-semibold">
                                                {lesson.date_parts?.day ?? '—'}
                                            </p>
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="line-clamp-2 font-medium">
                                                {lesson.display_title}
                                            </p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {[
                                                    lesson.classroom?.name,
                                                    lesson.series?.title,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {plural(
                                                    lesson.materials_count ?? 0,
                                                    'material',
                                                    'materiais',
                                                )}
                                                {' · '}
                                                {plural(
                                                    lesson.questions_count ?? 0,
                                                    'pergunta',
                                                    'perguntas',
                                                )}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            status={lesson.status}
                                            label={lesson.status_label}
                                        />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {classrooms.length > 0 && (
                    <section className="mt-10">
                        <h2 className="mb-3 flex items-center gap-2 text-lg font-semibold">
                            <Users className="size-5 text-primary" /> Minhas
                            classes
                        </h2>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {classrooms.map((classroom) => (
                                <div
                                    key={classroom.id}
                                    className="rounded-2xl border bg-card p-4"
                                >
                                    <p className="font-semibold">
                                        {classroom.name}
                                    </p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={agendaIndex(
                                                    classroom.slug,
                                                )}
                                            >
                                                <CalendarDays /> Agenda
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={membersIndex(
                                                    classroom.slug,
                                                )}
                                            >
                                                <Users /> Alunos
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={insights(classroom.slug)}
                                            >
                                                <ChartLine /> Evolução
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </Page>
        </>
    );
}

function plural(count: number, one: string, many: string): string {
    return `${count} ${count === 1 ? one : many}`;
}
