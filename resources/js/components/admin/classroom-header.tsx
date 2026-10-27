import { Link } from '@inertiajs/react';
import { ClipboardCheck, Ellipsis } from 'lucide-react';
import type { Crumb } from '@/components/page';
import { Breadcrumbs } from '@/components/page';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    edit,
    lessons,
    questions,
    report,
    show,
} from '@/routes/admin/classrooms';
import {
    index as meetingsIndex,
    today as meetingsToday,
} from '@/routes/admin/classrooms/meetings';
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

/** Dados do cartão do topo (ClassroomHeaderQuery). */
export type ClassroomHeaderData = {
    current_series: { id: number; title: string } | null;
    lesson_position: { index: number; total: number } | null;
    students: number;
    teachers: string[];
    can_edit: boolean;
    can_manage: boolean;
};

/**
 * Cartão do topo das páginas da classe, no padrão de página de projeto dos
 * painéis de administração: quem é a classe, a série atual, os números e
 * "Fazer chamada"; embaixo, as abas Visão geral, Lições, Alunos e Ajustes.
 */
export function ClassroomShell({
    classroom,
    header,
    active,
}: {
    classroom: Classroom;
    header: ClassroomHeaderData;
    active: 'geral' | 'licoes' | 'alunos' | 'ajustes';
}) {
    const tabs = [
        { key: 'geral', title: 'Visão geral', href: show.url(classroom.slug) },
        ...(header.can_manage
            ? [
                  {
                      key: 'licoes',
                      title: 'Lições',
                      href: lessons.url(classroom.slug),
                  },
              ]
            : []),
        {
            key: 'alunos',
            title: `Alunos · ${header.students}`,
            href: studentsIndex.url(classroom.slug),
        },
        ...(header.can_edit
            ? [
                  {
                      key: 'ajustes',
                      title: 'Ajustes',
                      href: edit.url(classroom.slug),
                  },
              ]
            : []),
    ];
    const teachers = header.teachers.join(' e ');

    return (
        <div className="mb-6 rounded-2xl border bg-card">
            <div className="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:gap-6 lg:p-6">
                <div className="flex min-w-0 flex-1 items-center gap-4">
                    <div
                        aria-hidden="true"
                        className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/15 font-serif text-xl font-semibold text-primary"
                    >
                        {classroom.name.charAt(0).toUpperCase()}
                    </div>
                    <div className="min-w-0">
                        <h1 className="font-serif text-2xl font-semibold tracking-tight sm:text-3xl">
                            {classroom.name}
                        </h1>
                        <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                            {teachers && <span>{teachers}</span>}
                            {header.current_series && (
                                <span className="inline-flex items-center rounded-md bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                    Série atual: {header.current_series.title}
                                </span>
                            )}
                        </p>
                    </div>
                </div>
                <dl className="flex gap-6 text-sm">
                    <div>
                        <dt className="sr-only">Alunos</dt>
                        <dd className="text-lg font-semibold">
                            {header.students}
                        </dd>
                        <dd className="text-xs text-muted-foreground">
                            {header.students === 1 ? 'aluno' : 'alunos'}
                        </dd>
                    </div>
                    {header.lesson_position && (
                        <div>
                            <dt className="sr-only">Lição da série</dt>
                            <dd className="text-lg font-semibold">
                                {header.lesson_position.index} de{' '}
                                {header.lesson_position.total}
                            </dd>
                            <dd className="text-xs text-muted-foreground">
                                lições da série
                            </dd>
                        </div>
                    )}
                </dl>
                <div className="flex gap-2">
                    {header.can_manage && (
                        <Button asChild className="flex-1 lg:flex-none">
                            <Link href={meetingsToday.url(classroom.slug)}>
                                <ClipboardCheck /> Fazer chamada
                            </Link>
                        </Button>
                    )}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label={`Mais da classe ${classroom.name}`}
                            >
                                <Ellipsis />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {header.can_manage && (
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={meetingsIndex.url(classroom.slug)}
                                    >
                                        Chamadas anteriores
                                    </Link>
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem asChild>
                                <Link href={report.url(classroom.slug)}>
                                    Relatório
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href={questions.url(classroom.slug)}>
                                    Dúvidas dos alunos
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
            <nav
                aria-label={`Classe ${classroom.name}`}
                className="flex gap-6 overflow-x-auto overflow-y-hidden border-t px-5 lg:px-6"
            >
                {tabs.map((tab) => (
                    <Link
                        key={tab.key}
                        href={tab.href}
                        aria-current={tab.key === active ? 'page' : undefined}
                        className={cn(
                            '-mb-px flex min-h-12 shrink-0 items-center border-b-2 border-transparent text-sm font-medium whitespace-nowrap text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            tab.key === active &&
                                'border-primary text-foreground',
                        )}
                    >
                        {tab.title}
                    </Link>
                ))}
            </nav>
        </div>
    );
}
