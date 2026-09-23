import { Head, Link } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { create, edit } from '@/routes/admin/classrooms';
import { index as members } from '@/routes/admin/classrooms/members';
import type { Classroom } from '@/types';

type Row = Classroom & {
    members_count: number;
    lessons_count: number;
    series_count: number;
};

export default function ClassroomsIndex({ classrooms }: { classrooms: Row[] }) {
    return (
        <>
            <Head title="Classes" />
            <Page width="wide">
                <PageHeader
                    title="Classes"
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> Nova classe
                            </Link>
                        </Button>
                    }
                />
                <ul className="grid gap-3 sm:grid-cols-2">
                    {classrooms.map((classroom) => (
                        <li
                            key={classroom.id}
                            className="rounded-2xl border bg-card p-4"
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
                            <p className="mt-3 text-sm text-muted-foreground">
                                {classroom.members_count} membros ·{' '}
                                {classroom.series_count} séries ·{' '}
                                {classroom.lessons_count} lições
                            </p>
                            <div className="mt-3 flex gap-2">
                                <Button asChild size="sm" variant="outline">
                                    <Link href={members(classroom.slug)}>
                                        <Users /> Membros
                                    </Link>
                                </Button>
                                <Button asChild size="sm" variant="ghost">
                                    <Link href={edit(classroom.slug)}>
                                        Editar
                                    </Link>
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            </Page>
        </>
    );
}
