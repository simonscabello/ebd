import { Link } from '@inertiajs/react';
import type { Crumb } from '@/components/page';
import { Breadcrumbs } from '@/components/page';
import { questions, report, show } from '@/routes/admin/classrooms';
import { index as meetingsIndex } from '@/routes/admin/classrooms/meetings';
import { index as studentsIndex } from '@/routes/admin/classrooms/students';
import { cn } from '@/lib/utils';
import type { Classroom } from '@/types';

export type ClassroomTab =
    | 'resumo'
    | 'domingos'
    | 'alunos'
    | 'duvidas'
    | 'relatorio';

/**
 * Topo das páginas de dentro de uma classe: o caminho (Classe › Domingos › …)
 * de volta à página da classe, que reúne tudo. As classes estão no menu.
 */
export function ClassroomHeader({
    classroom,
    active,
    crumbs = [],
}: {
    classroom: Classroom;
    active: ClassroomTab;
    /** Itens depois da aba (ex.: a data do domingo, o nome do aluno). */
    crumbs?: Crumb[];
}) {
    const tabs: { key: ClassroomTab; title: string; href: string }[] = [
        {
            key: 'resumo',
            title: classroom.name,
            href: show.url(classroom.slug),
        },
        {
            key: 'domingos',
            title: classroom.meeting_name_plural ?? 'Domingos',
            href: meetingsIndex.url(classroom.slug),
        },
        {
            key: 'alunos',
            title: 'Alunos',
            href: studentsIndex.url(classroom.slug),
        },
        {
            key: 'duvidas',
            title: 'Dúvidas',
            href: questions.url(classroom.slug),
        },
        {
            key: 'relatorio',
            title: 'Relatório',
            href: report.url(classroom.slug),
        },
    ];

    const current = tabs.find((tab) => tab.key === active) ?? tabs[0];

    // A página da classe já é o ponto de partida: não precisa de caminho.
    if (active === 'resumo' && crumbs.length === 0) {
        return null;
    }

    const trail: Crumb[] = [
        { title: classroom.name, href: show.url(classroom.slug) },
        ...(active === 'resumo'
            ? []
            : [{ title: current.title, href: current.href }]),
        ...crumbs,
    ];

    return <Breadcrumbs items={trail} className="mb-4 print:hidden" />;
}

/** Agenda | Alunos: as duas vistas da classe, no lugar das abas antigas. */
export function ClassroomTabs({
    classroom,
    active,
    students,
}: {
    classroom: Classroom;
    active: 'agenda' | 'alunos';
    students?: number;
}) {
    const items = [
        { key: 'agenda', title: 'Agenda', href: show.url(classroom.slug) },
        {
            key: 'alunos',
            title: students === undefined ? 'Alunos' : `Alunos · ${students}`,
            href: studentsIndex.url(classroom.slug),
        },
    ] as const;

    return (
        <nav
            aria-label={`Classe ${classroom.name}`}
            className="grid grid-cols-2 gap-1 rounded-xl border bg-card p-1 sm:inline-grid"
        >
            {items.map((item) => (
                <Link
                    key={item.key}
                    href={item.href}
                    aria-current={item.key === active ? 'page' : undefined}
                    className={cn(
                        'flex min-h-10 items-center justify-center rounded-lg px-4 text-sm text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        item.key === active &&
                            'bg-muted font-medium text-foreground',
                    )}
                >
                    {item.title}
                </Link>
            ))}
        </nav>
    );
}
