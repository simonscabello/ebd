import { Head, router } from '@inertiajs/react';
import {
    BookOpen,
    CalendarCheck,
    FileBarChart,
    Printer,
    Users,
} from 'lucide-react';
import type {
    MatrixStudent,
    MatrixSunday,
} from '@/components/admin/attendance-matrix';
import {
    AbsentMark,
    AttendanceMatrix,
    PresentMark,
} from '@/components/admin/attendance-matrix';
import { ClassroomHeader } from '@/components/admin/classroom-header';
import { Meter } from '@/components/admin/meter';
import { StatTile } from '@/components/admin/stat-tile';
import { EmptyState, Page, PageHeader, Section } from '@/components/page';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { dayMonth, longDate } from '@/lib/dates';
import { report } from '@/routes/admin/classrooms';
import type { Classroom } from '@/types';

type Props = {
    classroom: Classroom;
    period: {
        label: string;
        from: string | null;
        to: string;
        series_id: number | null;
    };
    options: { value: string; label: string }[];
    selected: string;
    sundays: MatrixSunday[];
    students: (MatrixStudent & { gender: string | null })[];
    totals: {
        sundays: number;
        present: number;
        expected: number;
        visitors: number;
        rate: number | null;
    };
    byGender: {
        gender: string | null;
        label: string;
        students: number;
        present: number;
        expected: number;
        rate: number | null;
    }[];
    homeStudy: {
        lesson_id: number;
        lesson_title: string;
        readings_total: number;
        readers: number;
        expected: number;
        rate: number | null;
        avg_days: number;
    }[];
    printedAt: string;
};

/**
 * Relatório da classe por série (revista): a chamada de cada aluno em cada
 * domingo, a frequência por gênero e o estudo em casa por lição. Imprime em
 * A4 deitado.
 */
export default function ClassroomReport({
    classroom,
    period,
    options,
    selected,
    sundays,
    students,
    totals,
    byGender,
    homeStudy,
    printedAt,
}: Props) {
    const range = period.from
        ? `${dayMonth(period.from)} a ${dayMonth(period.to)}`
        : `até ${dayMonth(period.to)}`;

    const choose = (value: string) => {
        const [kind, id] = value.split(':');
        router.get(
            report.url(classroom.slug, {
                query: kind === 'serie' ? { serie: id } : { periodo: '3m' },
            }),
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`Relatório · ${classroom.name}`} />
            <style>{'@page { size: A4 landscape; margin: 12mm; }'}</style>

            <Page width="wide">
                <ClassroomHeader classroom={classroom} active="relatorio" />

                <p className="mb-2 hidden text-sm text-muted-foreground print:block">
                    EBD · Classe {classroom.name} · impresso em{' '}
                    {longDate(printedAt)}
                </p>
                <PageHeader
                    title={`Relatório · ${period.label}`}
                    description={`${range} · ${totals.sundays} ${totals.sundays === 1 ? 'domingo' : 'domingos'} com chamada. Cada aluno conta a partir de quando entrou na classe.`}
                    actions={
                        <div className="flex flex-wrap gap-2 print:hidden">
                            <label htmlFor="report-period" className="sr-only">
                                Período do relatório
                            </label>
                            <NativeSelect
                                id="report-period"
                                className="sm:w-64"
                                value={selected}
                                onChange={(event) => choose(event.target.value)}
                            >
                                {options.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </NativeSelect>
                            <Button
                                variant="outline"
                                onClick={() => window.print()}
                            >
                                <Printer /> Imprimir
                            </Button>
                        </div>
                    }
                />

                {sundays.length === 0 ? (
                    <EmptyState
                        icon={<FileBarChart />}
                        title="Nenhum domingo com chamada neste período"
                    >
                        A chamada é feita no Modo Domingo ou na página de cada
                        domingo.
                    </EmptyState>
                ) : (
                    <div className="space-y-10">
                        <div className="grid gap-3 sm:grid-cols-3 print:grid-cols-3">
                            <StatTile
                                icon={<CalendarCheck />}
                                label="Frequência média"
                                value={
                                    totals.rate === null
                                        ? '—'
                                        : `${totals.rate}%`
                                }
                                hint={`${totals.present} de ${totals.expected} presenças`}
                            />
                            <StatTile
                                icon={<Users />}
                                label="Alunos no período"
                                value={String(students.length)}
                                hint={byGender
                                    .map(
                                        (g) =>
                                            `${g.students} ${g.label.toLowerCase()}`,
                                    )
                                    .join(' · ')}
                            />
                            <StatTile
                                icon={<Users />}
                                label="Visitantes"
                                value={String(totals.visitors)}
                                hint="somando todos os domingos"
                            />
                        </div>

                        {byGender.length > 0 && (
                            <Section title="Frequência por gênero">
                                <ul className="grid gap-3 sm:grid-cols-3">
                                    {byGender.map((group) => (
                                        <li
                                            key={group.label}
                                            className="rounded-2xl border bg-card p-4"
                                        >
                                            <p className="flex items-baseline justify-between gap-2">
                                                <span className="font-medium">
                                                    {group.label}
                                                </span>
                                                <span className="font-serif text-2xl font-semibold tabular-nums">
                                                    {group.rate === null
                                                        ? '—'
                                                        : `${group.rate}%`}
                                                </span>
                                            </p>
                                            <Meter
                                                value={group.rate}
                                                className="mt-2"
                                            />
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                {group.students}{' '}
                                                {group.students === 1
                                                    ? 'aluno'
                                                    : 'alunos'}{' '}
                                                · {group.present} de{' '}
                                                {group.expected} presenças
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            </Section>
                        )}

                        <Section
                            title="Chamada"
                            icon={<CalendarCheck />}
                            description={
                                <span className="flex flex-wrap items-center gap-x-4 gap-y-1">
                                    <span className="inline-flex items-center gap-1.5">
                                        <PresentMark label="" /> presente
                                    </span>
                                    <span className="inline-flex items-center gap-1.5">
                                        <AbsentMark label="" /> faltou
                                    </span>
                                    <span>
                                        em branco: ainda não era da classe
                                    </span>
                                </span>
                            }
                        >
                            <AttendanceMatrix
                                sundays={sundays}
                                students={students}
                            />
                        </Section>

                        {homeStudy.length > 0 && (
                            <Section
                                title="Estudo em casa"
                                icon={<BookOpen />}
                                description="Alunos que marcaram leitura na semana de cada lição."
                            >
                                <ul className="divide-y rounded-2xl border bg-card">
                                    {homeStudy.map((lesson) => (
                                        <li
                                            key={lesson.lesson_id}
                                            className="px-4 py-3 text-sm"
                                        >
                                            <p className="font-medium">
                                                {lesson.lesson_title}
                                            </p>
                                            <p className="mt-0.5 text-muted-foreground">
                                                {studySummary(lesson)}
                                            </p>
                                            <Meter
                                                value={lesson.rate}
                                                className="mt-2"
                                            />
                                        </li>
                                    ))}
                                </ul>
                            </Section>
                        )}
                    </div>
                )}
            </Page>
        </>
    );
}

function studySummary(lesson: Props['homeStudy'][number]): string {
    if (lesson.readers === 0) {
        return lesson.expected === 1
            ? 'O aluno não marcou leitura'
            : `Nenhum dos ${lesson.expected} alunos marcou leitura`;
    }

    const days = String(lesson.avg_days).replace('.', ',');
    const plan =
        lesson.readings_total > 1 ? ` (plano de ${lesson.readings_total})` : '';

    return `${lesson.readers} de ${lesson.expected} leram${lesson.rate !== null ? ` (${lesson.rate}%)` : ''} · média de ${days} ${lesson.avg_days === 1 ? 'dia' : 'dias'}${plan}`;
}
