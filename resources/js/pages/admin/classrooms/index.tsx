import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Plus } from 'lucide-react';
import { EmptyState, Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { plural } from '@/lib/utils';
import { create, edit, show } from '@/routes/admin/classrooms';
import type { Classroom } from '@/types';

type Row = Classroom & {
    students_count: number;
    lessons_count: number;
    series_count: number;
};

/**
 * Classes: cada professor vê as suas; a administração vê todas e cadastra.
 */
export default function ClassroomsIndex({
    classrooms,
    canCreate,
}: {
    classrooms: Row[];
    canCreate: boolean;
}) {
    return (
        <>
            <Head title="Classes" />
            <Page width="wide">
                <PageHeader
                    title="Classes"
                    actions={
                        canCreate && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> Nova classe
                                </Link>
                            </Button>
                        )
                    }
                />
                {classrooms.length === 0 ? (
                    <EmptyState title="Nenhuma classe ainda" />
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2">
                        {classrooms.map((classroom) => (
                            <li
                                key={classroom.id}
                                className="flex flex-col rounded-2xl border bg-card p-4"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <p className="font-serif text-lg font-semibold">
                                        {classroom.name}
                                    </p>
                                    {!classroom.is_active && (
                                        <span className="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                            Inativa
                                        </span>
                                    )}
                                </div>
                                {classroom.description && (
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {classroom.description}
                                    </p>
                                )}
                                <p className="mt-3 flex-1 text-sm text-muted-foreground">
                                    {plural(
                                        classroom.students_count,
                                        'aluno',
                                        'alunos',
                                    )}{' '}
                                    ·{' '}
                                    {plural(
                                        classroom.series_count,
                                        'série',
                                        'séries',
                                    )}{' '}
                                    ·{' '}
                                    {plural(
                                        classroom.lessons_count,
                                        'lição',
                                        'lições',
                                    )}
                                </p>
                                <div className="mt-3 flex gap-2">
                                    <Button asChild size="sm">
                                        <Link href={show(classroom.slug)}>
                                            Abrir classe <ChevronRight />
                                        </Link>
                                    </Button>
                                    {canCreate && (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link href={edit(classroom.slug)}>
                                                Editar
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Page>
        </>
    );
}
