import { Head, Link } from '@inertiajs/react';
import { ChevronRight, GraduationCap, Search, Users } from 'lucide-react';
import { useState } from 'react';
import type { IssuedLink } from '@/components/admin/access-link-dialog';
import { AccessLinkDialog } from '@/components/admin/access-link-dialog';
import { ClassroomHeader } from '@/components/admin/classroom-header';
import { AddByEmailDialog } from '@/components/admin/students/add-by-email-dialog';
import { AddStudentDialog } from '@/components/admin/students/add-student-dialog';
import { EmptyState, Page, PageHeader, Section } from '@/components/page';
import { Input } from '@/components/ui/input';
import { dayMonth } from '@/lib/dates';
import { cn } from '@/lib/utils';
import { edit } from '@/routes/admin/classrooms';
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
    needs_attention: boolean;
    reasons: string[];
    is_new: boolean;
    birthday: 'today' | 'soon' | null;
    access: 'ok' | 'pending_profile' | 'never_entered';
};

type Props = {
    classroom: Classroom;
    today: string;
    period: { label: string; series_id: number | null };
    students: StudentRow[];
    teachers: { id: number; name: string }[];
    canAssignTeachers: boolean;
};

/**
 * Alunos da classe com a frequência do período e os avisos de cada um. Cada
 * linha abre a ficha do aluno.
 */
export default function ClassroomStudents({
    classroom,
    period,
    students,
    teachers,
    canAssignTeachers,
}: Props) {
    const [issued, setIssued] = useState<IssuedLink | null>(null);
    const [query, setQuery] = useState('');
    const needle = normalize(query);
    const visible = needle
        ? students.filter((s) => normalize(s.name).includes(needle))
        : students;

    return (
        <>
            <Head title={`Alunos · ${classroom.name}`} />

            <Page width="wide">
                <ClassroomHeader classroom={classroom} active="alunos" />
                <PageHeader
                    title="Alunos"
                    description={`${students.length} ${students.length === 1 ? 'aluno' : 'alunos'} · frequência ${period.series_id ? `na série ${period.label}` : `nos ${period.label.toLowerCase()}`}`}
                    actions={
                        <>
                            <AddByEmailDialog classroom={classroom} />
                            <AddStudentDialog
                                classroom={classroom}
                                onIssued={setIssued}
                            />
                        </>
                    }
                />

                {students.length === 0 ? (
                    <EmptyState icon={<Users />} title="Nenhum aluno ainda">
                        Adicione os alunos com nome e WhatsApp e envie o link de
                        acesso de cada um.
                    </EmptyState>
                ) : (
                    <>
                        <div className="relative mb-4">
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
                        <ul className="divide-y rounded-2xl border bg-card">
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
                                    Ninguém com esse nome.
                                </li>
                            )}
                        </ul>
                    </>
                )}

                <Section
                    title="Professores"
                    icon={<GraduationCap />}
                    className="mt-10"
                >
                    <p className="text-sm text-muted-foreground">
                        {teachers.length > 0
                            ? teachers.map((t) => t.name).join(', ')
                            : 'Nenhum professor na classe.'}
                        {canAssignTeachers && (
                            <>
                                {' · '}
                                <Link
                                    href={edit(classroom.slug)}
                                    className="font-medium text-primary hover:underline"
                                >
                                    Gerenciar professores
                                </Link>
                            </>
                        )}
                    </p>
                </Section>
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
    const { frequency } = student;

    return (
        <Link
            href={show({ classroom: classroom.slug, user: student.id })}
            className="flex items-center gap-3 px-4 py-3 hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
        >
            <div className="min-w-0 flex-1">
                <p className="flex flex-wrap items-center gap-1.5">
                    <span className="font-medium">{student.name}</span>
                    {student.birthday && (
                        <Chip tone="highlight">
                            🎂{' '}
                            {student.birthday === 'today'
                                ? 'hoje'
                                : 'esta semana'}
                        </Chip>
                    )}
                    {student.needs_attention && (
                        <Chip tone="warning">precisa de atenção</Chip>
                    )}
                    {student.is_new && <Chip tone="info">novo</Chip>}
                    {student.access === 'never_entered' && (
                        <Chip>nunca entrou</Chip>
                    )}
                    {student.access === 'pending_profile' && (
                        <Chip>cadastro pendente</Chip>
                    )}
                </p>
                <p className="mt-0.5 text-sm text-muted-foreground">
                    {frequency.expected > 0
                        ? `Frequência ${frequency.present} de ${frequency.expected}${frequency.rate !== null ? ` (${frequency.rate}%)` : ''}`
                        : 'Sem chamada desde que entrou'}
                    {student.last_present_on &&
                        ` · última presença ${dayMonth(student.last_present_on)}`}
                </p>
                {student.reasons.length > 0 && (
                    <p className="text-xs text-warning-foreground">
                        {student.reasons.join(' · ')}
                    </p>
                )}
            </div>
            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
        </Link>
    );
}

function Chip({
    tone = 'muted',
    children,
}: {
    tone?: 'muted' | 'warning' | 'info' | 'highlight';
    children: React.ReactNode;
}) {
    return (
        <span
            className={cn(
                'rounded-full px-2 py-0.5 text-xs font-medium',
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
