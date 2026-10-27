import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Search, Users } from 'lucide-react';
import { useState } from 'react';
import type { IssuedLink } from '@/components/admin/access-link-dialog';
import { AccessLinkDialog } from '@/components/admin/access-link-dialog';
import { ClassroomShell } from '@/components/admin/classroom-header';
import type { ClassroomHeaderData } from '@/components/admin/classroom-header';
import { AddByEmailDialog } from '@/components/admin/students/add-by-email-dialog';
import { AddStudentDialog } from '@/components/admin/students/add-student-dialog';
import { EmptyState, Page } from '@/components/page';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { show } from '@/routes/admin/classrooms/students';
import type { Classroom } from '@/types';

type StudentRow = {
    id: number;
    name: string;
    phone: string | null;
    frequency: {
        present: number;
        expected: number;
        rate: number | null;
    };
    last_present_on: string | null;
    /** Presença nos últimos domingos com chamada, do mais antigo ao mais recente. */
    recent: boolean[];
    /** Já marcou leitura da lição atual; null sem lição atual. */
    read_current: boolean | null;
    needs_attention: boolean;
    reasons: string[];
    is_new: boolean;
    birthday: 'today' | 'soon' | null;
    access: 'ok' | 'pending_profile' | 'never_entered';
};

type Filter = 'all' | 'attention' | 'birthday';

const FILTERS: Record<
    Filter,
    { label: string; test: (student: StudentRow) => boolean }
> = {
    all: { label: 'Todos', test: () => true },
    attention: {
        label: 'Precisam de atenção',
        // A mesma conta da Visão geral.
        test: (student) => student.needs_attention,
    },
    birthday: {
        label: 'Aniversário',
        test: (student) => student.birthday !== null,
    },
};

type Props = {
    classroom: Classroom;
    header: ClassroomHeaderData;
    students: StudentRow[];
};

/**
 * Alunos da classe numa lista simples: a situação de cada um, os últimos
 * domingos e se já leu a lição atual. Clicou no nome, abre a página do
 * aluno com tudo.
 */
export default function ClassroomStudents({
    classroom,
    header,
    students,
}: Props) {
    const [issued, setIssued] = useState<IssuedLink | null>(null);
    const [query, setQuery] = useState('');
    const [filter, setFilter] = useState<Filter>('all');
    const needle = normalize(query);
    const visible = students.filter(
        (s) =>
            FILTERS[filter].test(s) &&
            (!needle || normalize(s.name).includes(needle)),
    );

    return (
        <>
            <Head title={`Alunos · ${classroom.name}`} />

            <Page width="wide">
                <ClassroomShell
                    classroom={classroom}
                    header={header}
                    active="alunos"
                />
                {students.length === 0 ? (
                    <EmptyState icon={<Users />} title="Nenhum aluno ainda">
                        <p>
                            Adicione os alunos com nome e WhatsApp e envie o
                            link de acesso de cada um.
                        </p>
                        <div className="mt-4 flex flex-wrap justify-center gap-2">
                            <AddByEmailDialog classroom={classroom} />
                            <AddStudentDialog
                                classroom={classroom}
                                onIssued={setIssued}
                            />
                        </div>
                    </EmptyState>
                ) : (
                    <>
                        <div className="mb-4 flex flex-col gap-3">
                            <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                                <div
                                    className="flex flex-wrap gap-2"
                                    role="group"
                                    aria-label="Filtrar alunos"
                                >
                                    {(Object.keys(FILTERS) as Filter[]).map(
                                        (key) => {
                                            const count = students.filter(
                                                FILTERS[key].test,
                                            ).length;

                                            return (
                                                (key === 'all' ||
                                                    count > 0) && (
                                                    <button
                                                        key={key}
                                                        type="button"
                                                        aria-pressed={
                                                            filter === key
                                                        }
                                                        onClick={() =>
                                                            setFilter(key)
                                                        }
                                                        className={cn(
                                                            'min-h-10 rounded-lg border px-3.5 text-sm text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                            filter === key &&
                                                                'border-transparent bg-accent font-medium text-accent-foreground',
                                                        )}
                                                    >
                                                        {FILTERS[key].label}{' '}
                                                        <span className="text-xs">
                                                            {count}
                                                        </span>
                                                    </button>
                                                )
                                            );
                                        },
                                    )}
                                </div>
                                <div className="flex flex-wrap gap-2 sm:ml-auto">
                                    <AddByEmailDialog classroom={classroom} />
                                    <AddStudentDialog
                                        classroom={classroom}
                                        onIssued={setIssued}
                                    />
                                </div>
                            </div>
                            <div className="relative sm:w-72">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="search"
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Buscar pelo nome"
                                    aria-label="Buscar aluno pelo nome"
                                    className="pl-9"
                                />
                            </div>
                        </div>

                        <div className="overflow-hidden rounded-2xl border bg-card">
                            <div className="hidden grid-cols-[minmax(0,1fr)_12rem_13rem_7rem_1rem] gap-4 border-b px-4 py-2.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase lg:grid">
                                <span>Aluno</span>
                                <span>Situação</span>
                                <span>Últimos domingos</span>
                                <span>Lição atual</span>
                                <span />
                            </div>
                            <ul className="divide-y">
                                {visible.map((student) => (
                                    <li key={student.id}>
                                        <StudentLine
                                            classroom={classroom}
                                            student={student}
                                        />
                                    </li>
                                ))}
                                {visible.length === 0 && (
                                    <li className="px-4 py-6 text-center text-sm text-muted-foreground">
                                        Ninguém aqui.
                                    </li>
                                )}
                            </ul>
                        </div>
                    </>
                )}
            </Page>

            <AccessLinkDialog link={issued} onClose={() => setIssued(null)} />
        </>
    );
}

function StudentLine({
    classroom,
    student,
}: {
    classroom: Classroom;
    student: StudentRow;
}) {
    const situation = situationOf(student);
    const present = student.recent.filter(Boolean).length;
    const initials = student.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

    return (
        <Link
            href={show({ classroom: classroom.slug, user: student.id })}
            className="grid grid-cols-[minmax(0,1fr)_1rem] items-center gap-x-4 gap-y-2 px-4 py-3 hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none lg:grid-cols-[minmax(0,1fr)_12rem_13rem_7rem_1rem]"
        >
            <span className="flex min-w-0 items-center gap-3">
                <span
                    aria-hidden="true"
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/15 text-xs font-semibold text-primary',
                        situation.tone === 'warning' &&
                            'bg-warning-soft text-warning-foreground',
                    )}
                >
                    {initials}
                </span>
                <span className="truncate font-medium">{student.name}</span>
            </span>
            <ChevronRight className="size-4 shrink-0 text-muted-foreground lg:order-last" />
            <span className="col-span-2 flex flex-wrap items-center gap-x-4 gap-y-2 pl-12 lg:contents">
                <span>
                    <Chip tone={situation.tone}>{situation.label}</Chip>
                </span>
                <span className="flex items-center gap-2">
                    {student.recent.length > 0 ? (
                        <>
                            <span
                                className="flex gap-1"
                                aria-label={`Veio a ${present} dos últimos ${student.recent.length} domingos`}
                            >
                                {student.recent.map((wasPresent, index) => (
                                    <span
                                        key={index}
                                        className={cn(
                                            'size-2.5 rounded-[3px]',
                                            wasPresent
                                                ? 'bg-primary'
                                                : 'bg-warning-foreground/40',
                                        )}
                                    />
                                ))}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {present} de {student.recent.length}
                            </span>
                        </>
                    ) : (
                        <span className="text-xs text-muted-foreground">
                            Sem chamada ainda
                        </span>
                    )}
                </span>
                <span className="text-sm text-muted-foreground">
                    {student.read_current === null
                        ? '—'
                        : student.read_current
                          ? 'Leu'
                          : 'Não leu'}
                </span>
            </span>
        </Link>
    );
}

/** A situação que mais pede atenção vira o selo da linha. */
function situationOf(student: StudentRow): {
    label: string;
    tone: 'success' | 'warning' | 'info' | 'highlight' | 'muted';
} {
    // O mesmo motivo da Visão geral ("11 dias sem leitura" não é o mesmo que "não leu a lição atual").
    if (student.needs_attention && student.reasons.length > 0) {
        const reason = student.reasons[0];

        return {
            label: reason.charAt(0).toUpperCase() + reason.slice(1),
            tone: 'warning',
        };
    }

    if (student.birthday) {
        return {
            label:
                student.birthday === 'today'
                    ? 'Aniversário hoje'
                    : 'Aniversário na semana',
            tone: 'highlight',
        };
    }

    if (student.access === 'never_entered') {
        return { label: 'Nunca entrou no app', tone: 'muted' };
    }

    if (student.access === 'pending_profile') {
        return { label: 'Cadastro pendente', tone: 'muted' };
    }

    if (student.is_new) {
        return { label: 'Novo', tone: 'info' };
    }

    return { label: 'Em dia', tone: 'success' };
}

function Chip({
    tone = 'muted',
    children,
}: {
    tone?: 'success' | 'muted' | 'warning' | 'info' | 'highlight';
    children: React.ReactNode;
}) {
    return (
        <span
            className={cn(
                'inline-flex max-w-full truncate rounded-md px-2 py-0.5 text-xs font-medium',
                tone === 'success' && 'bg-success-soft text-success-foreground',
                tone === 'muted' && 'bg-muted text-muted-foreground',
                tone === 'warning' && 'bg-warning-soft text-warning-foreground',
                tone === 'info' &&
                    'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
                tone === 'highlight' &&
                    'bg-highlight text-highlight-foreground',
            )}
        >
            {children}
        </span>
    );
}

function normalize(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim();
}
