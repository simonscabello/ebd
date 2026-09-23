import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRight,
    CalendarDays,
    Layers,
    Plus,
    Users,
} from 'lucide-react';
import { StatusBadge } from '@/components/admin/status-badge';
import { EmptyState, Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { index as membersIndex } from '@/routes/admin/classrooms/members';
import {
    create as createLesson,
    edit as editLesson,
} from '@/routes/admin/lessons';
import { create as createSeries } from '@/routes/admin/series';
import type { Classroom, Lesson } from '@/types';

type Props = {
    classrooms: Classroom[];
    upcoming: Lesson[];
    pendingCompletion: Lesson[];
    isAdmin: boolean;
};

export default function AdminDashboard({
    classrooms,
    upcoming,
    pendingCompletion,
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

                {pendingCompletion.length > 0 && (
                    <section className="mb-8 rounded-2xl border border-amber-300/60 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40">
                        <h2 className="flex items-center gap-2 font-semibold text-amber-900 dark:text-amber-200">
                            <AlertCircle className="size-5" /> Aulas que já
                            aconteceram
                        </h2>
                        <p className="mt-1 text-sm text-amber-900/80 dark:text-amber-200/80">
                            Conclua para arquivá-las na biblioteca.
                        </p>
                        <ul className="mt-3 space-y-2">
                            {pendingCompletion.map((lesson) => (
                                <li key={lesson.id}>
                                    <Link
                                        href={editLesson(lesson.id)}
                                        className="flex items-center justify-between rounded-xl bg-card px-3 py-2.5 text-sm hover:bg-muted"
                                    >
                                        <span>
                                            <span className="font-medium">
                                                {lesson.title}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {' '}
                                                · {lesson.date_short}
                                            </span>
                                        </span>
                                        <ArrowRight className="size-4 text-muted-foreground" />
                                    </Link>
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
                                            <p className="truncate font-medium">
                                                {lesson.title}
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
                                                {lesson.materials_count ?? 0}{' '}
                                                materiais ·{' '}
                                                {lesson.questions_count ?? 0}{' '}
                                                perguntas
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
                                <Link
                                    key={classroom.id}
                                    href={membersIndex(classroom.slug)}
                                    className="rounded-2xl border bg-card p-4 hover:border-primary/40"
                                >
                                    <p className="font-semibold">
                                        {classroom.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Ver e adicionar alunos
                                    </p>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
            </Page>
        </>
    );
}
