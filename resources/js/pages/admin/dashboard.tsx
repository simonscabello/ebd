import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CalendarCheck,
    CalendarDays,
    ChevronRight,
    Layers,
    Plus,
    Presentation,
    Users,
} from 'lucide-react';
import { HelpHint } from '@/components/admin/help-hint';
import { StatusBadge } from '@/components/admin/status-badge';
import { EmptyState, Page, PageHeader, Section } from '@/components/page';
import { Button } from '@/components/ui/button';
import { dateTile, dayMonth } from '@/lib/dates';
import { show as classroomPage } from '@/routes/admin/classrooms';
import { show as meetingPage } from '@/routes/admin/classrooms/meetings';
import {
    create as createLesson,
    edit as editLesson,
} from '@/routes/admin/lessons';
import { create as createSeries } from '@/routes/admin/series';
import { sunday } from '@/routes/lessons';
import type { Classroom, Lesson, LessonStatus } from '@/types';

type ClassroomCard = Classroom & {
    students_count: number;
    week: {
        meeting_id: number;
        held_on: string;
        is_today: boolean;
        has_attendance: boolean;
        title: string | null;
        lesson: {
            slug: string;
            display_title: string;
            status: LessonStatus;
        } | null;
    } | null;
};

type Pending = {
    id: number;
    held_on: string;
    lesson: string | null;
    title: string | null;
    classroom: { name: string; slug: string };
};

type Props = {
    classrooms: ClassroomCard[];
    pending: Pending[];
    upcoming: (Lesson & { next_meeting_on: string | null })[];
};

/**
 * Painel da gestão: cada classe com o que vem neste domingo, os domingos que
 * ficaram pendentes e as próximas lições a preparar.
 */
export default function AdminDashboard({
    classrooms,
    pending,
    upcoming,
}: Props) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Painel" />

            <Page width="wide">
                <PageHeader
                    eyebrow="Gestão da EBD"
                    title={`Olá, ${auth.user?.first_name ?? ''}`}
                    description={
                        classrooms.length
                            ? 'O que vem por aí nas suas classes.'
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

                <HelpHint />

                <div className="space-y-10">
                    {classrooms.length > 0 && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            {classrooms.map((classroom) => (
                                <ClassroomCardView
                                    key={classroom.id}
                                    classroom={classroom}
                                />
                            ))}
                        </div>
                    )}

                    {pending.length > 0 && (
                        <section className="rounded-2xl border border-warning/50 bg-warning-soft p-4">
                            <h2 className="flex items-center gap-2 font-semibold text-warning-foreground">
                                <AlertCircle className="size-5" /> Domingos para
                                confirmar
                            </h2>
                            <p className="mt-1 text-sm text-warning-foreground/80">
                                Já passaram e continuam “planejados”. Abra o
                                domingo para fazer a chamada ou marcar se teve
                                aula.
                            </p>
                            <ul className="mt-3 space-y-2">
                                {pending.map((meeting) => (
                                    <li key={meeting.id}>
                                        <Link
                                            href={meetingPage({
                                                classroom:
                                                    meeting.classroom.slug,
                                                meeting: meeting.id,
                                            })}
                                            className="flex items-center gap-3 rounded-xl bg-card px-3 py-2.5 text-sm hover:bg-muted/60"
                                        >
                                            <span className="min-w-0 flex-1">
                                                <span className="font-medium">
                                                    {dayMonth(meeting.held_on)}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {' · '}
                                                    {meeting.lesson ??
                                                        meeting.title ??
                                                        'Lição a definir'}
                                                    {classrooms.length > 1 &&
                                                        ` · ${meeting.classroom.name}`}
                                                </span>
                                            </span>
                                            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    <Section title="Próximas lições" icon={<CalendarDays />}>
                        {upcoming.length === 0 ? (
                            <EmptyState
                                icon={<CalendarDays />}
                                title="Nenhuma lição planejada"
                            >
                                Crie a próxima lição para que os alunos possam
                                se preparar.
                            </EmptyState>
                        ) : (
                            <ul className="divide-y rounded-2xl border bg-card">
                                {upcoming.map((lesson) => {
                                    const tile = lesson.next_meeting_on
                                        ? dateTile(lesson.next_meeting_on)
                                        : null;

                                    return (
                                        <li key={lesson.id}>
                                            <Link
                                                href={editLesson(lesson.id)}
                                                className="flex items-center gap-4 px-4 py-3.5 hover:bg-muted/50"
                                            >
                                                <div className="w-12 shrink-0 text-center">
                                                    <p className="text-xs text-muted-foreground uppercase">
                                                        {tile?.weekday ?? 'sem'}
                                                    </p>
                                                    <p className="font-serif text-2xl font-semibold">
                                                        {tile?.day ?? '—'}
                                                    </p>
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="line-clamp-2 font-medium">
                                                        {lesson.display_title}
                                                    </p>
                                                    <p className="truncate text-sm text-muted-foreground">
                                                        {[
                                                            lesson.next_meeting_on
                                                                ? dayMonth(
                                                                      lesson.next_meeting_on,
                                                                  )
                                                                : 'sem domingo',
                                                            lesson.classroom
                                                                ?.name,
                                                            lesson.series
                                                                ?.title,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </p>
                                                </div>
                                                <StatusBadge
                                                    status={lesson.status}
                                                    label={lesson.status_label}
                                                />
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </Section>
                </div>
            </Page>
        </>
    );
}

function ClassroomCardView({ classroom }: { classroom: ClassroomCard }) {
    const { week } = classroom;
    const hub = classroomPage(classroom.slug);

    return (
        <section className="flex flex-col rounded-2xl border bg-card p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="font-serif text-xl font-semibold">
                        {classroom.name}
                    </h2>
                    <p className="flex items-center gap-1 text-sm text-muted-foreground">
                        <Users className="size-3.5" />{' '}
                        {classroom.students_count}{' '}
                        {classroom.students_count === 1 ? 'aluno' : 'alunos'}
                    </p>
                </div>
            </div>

            <div className="mt-4 flex-1 rounded-xl bg-muted/50 px-3 py-2.5 text-sm">
                {week ? (
                    <>
                        <p className="font-medium text-primary">
                            {week.is_today
                                ? 'Hoje'
                                : `Próximo domingo · ${dayMonth(week.held_on)}`}
                        </p>
                        <p className="mt-0.5 line-clamp-2">
                            {week.lesson?.display_title ??
                                week.title ??
                                'Lição a definir'}
                            {week.lesson?.status === 'draft' && (
                                <StatusBadge
                                    status="draft"
                                    label="Rascunho"
                                    className="ml-2 align-middle"
                                />
                            )}
                        </p>
                        {week.has_attendance && (
                            <p className="mt-1 flex items-center gap-1 text-success-foreground">
                                <CalendarCheck className="size-3.5" /> Chamada
                                feita
                            </p>
                        )}
                    </>
                ) : (
                    <p className="text-muted-foreground">
                        Nenhum domingo planejado.
                    </p>
                )}
            </div>

            <div className="mt-4 flex flex-wrap gap-2">
                <Button asChild>
                    <Link href={hub}>
                        Abrir classe <ChevronRight />
                    </Link>
                </Button>
                {week?.lesson && (
                    <Button asChild variant="outline">
                        <Link
                            href={sunday.url(week.lesson.slug, {
                                query: { voltar: hub.url },
                            })}
                        >
                            <Presentation /> Modo Domingo
                        </Link>
                    </Button>
                )}
            </div>
        </section>
    );
}
