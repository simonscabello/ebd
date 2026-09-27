import { Link } from '@inertiajs/react';
import type { Crumb } from '@/components/page';
import { Breadcrumbs } from '@/components/page';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import { show } from '@/routes/admin/classrooms';
import { index as meetingsIndex } from '@/routes/admin/classrooms/meetings';
import { index as membersIndex } from '@/routes/admin/classrooms/members';
import type { Classroom } from '@/types';

export type ClassroomTab = 'resumo' | 'domingos' | 'alunos';

/**
 * Topo de toda página de uma classe: o caminho (Gestão › Classe › …) e as
 * abas da classe. A página continua com o próprio PageHeader, sem caminho.
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
        { key: 'resumo', title: 'Resumo', href: show.url(classroom.slug) },
        {
            key: 'domingos',
            title: 'Domingos',
            href: meetingsIndex.url(classroom.slug),
        },
        {
            key: 'alunos',
            title: 'Alunos',
            href: membersIndex.url(classroom.slug),
        },
    ];

    const current = tabs.find((tab) => tab.key === active) ?? tabs[0];
    const trail: Crumb[] = [
        { title: 'Gestão', href: dashboard.url() },
        { title: classroom.name, href: show.url(classroom.slug) },
        ...(active === 'resumo' && crumbs.length === 0
            ? []
            : [{ title: current.title, href: current.href }]),
        ...crumbs,
    ];

    return (
        <div className="mb-6 print:hidden">
            <Breadcrumbs items={trail} className="mb-3" />
            <nav
                aria-label={`Classe ${classroom.name}`}
                className="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0"
            >
                <ul className="flex min-w-max gap-1 border-b">
                    {tabs.map((tab) => {
                        const selected = tab.key === active;

                        return (
                            <li key={tab.key}>
                                <Link
                                    href={tab.href}
                                    aria-current={selected ? 'page' : undefined}
                                    className={cn(
                                        '-mb-px inline-flex min-h-11 items-center border-b-2 border-transparent px-3 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        selected &&
                                            'border-primary text-foreground',
                                    )}
                                >
                                    {tab.title}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </nav>
        </div>
    );
}
