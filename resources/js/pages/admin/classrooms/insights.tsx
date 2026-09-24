import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    BookOpen,
    CalendarCheck,
    FileText,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState, Page, PageHeader, Section } from '@/components/page';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { show as studentPage } from '@/routes/admin/classrooms/students';
import { report } from '@/routes/admin/series';
import type { Classroom, Series } from '@/types';

type Insights = {
    kpis: {
        students: number;
        attendance_rate: number | null;
        studying_rate: number | null;
        at_risk: number;
    };
    meetings: {
        id: number;
        date_short: string;
        lesson: string | null;
        taken: boolean;
        present: number;
        enrolled: number;
        visitors: number;
        rate: number | null;
    }[];
    lessons: {
        id: number;
        title: string | null;
        readers: number;
        readers_rate: number;
        avg_days: number;
    }[];
    students: {
        id: number;
        name: string;
        missed_in_a_row: number;
        last_read: string | null;
        last_present: string | null;
        at_risk: boolean;
        reasons: string[];
        is_new: boolean;
    }[];
    thresholds: { missed_meetings: number; inactive_days: number };
};

type Props = {
    classroom: Classroom;
    insights: Insights;
    series: Series[];
};

export default function ClassroomInsights({
    classroom,
    insights,
    series,
}: Props) {
    const atRisk = insights.students.filter((s) => s.at_risk);

    return (
        <>
            <Head title={`Evolução · ${classroom.name}`} />
            <Page width="wide">
                <PageHeader
                    eyebrow={`Classe ${classroom.name}`}
                    title="Evolução da classe"
                    description="Presença nos domingos e estudo em casa. As anotações dos alunos são privadas e não aparecem aqui."
                />

                <div className="mb-10 grid grid-cols-2 gap-3 md:grid-cols-4">
                    <Kpi
                        icon={<Users />}
                        label="Alunos"
                        value={`${insights.kpis.students}`}
                    />
                    <Kpi
                        icon={<CalendarCheck />}
                        label="Presença (últimos 4)"
                        value={
                            insights.kpis.attendance_rate === null
                                ? '—'
                                : `${insights.kpis.attendance_rate}%`
                        }
                    />
                    <Kpi
                        icon={<BookOpen />}
                        label="Estudaram em casa (7 dias)"
                        value={
                            insights.kpis.studying_rate === null
                                ? '—'
                                : `${insights.kpis.studying_rate}%`
                        }
                    />
                    <Kpi
                        icon={<AlertTriangle />}
                        label="Precisam de atenção"
                        value={`${insights.kpis.at_risk}`}
                        tone={insights.kpis.at_risk > 0 ? 'warning' : 'default'}
                    />
                </div>

                <div className="space-y-12">
                    <Section
                        title="Alunos que precisam de atenção"
                        icon={<AlertTriangle />}
                        description={`${insights.thresholds.missed_meetings} faltas seguidas ou ${insights.thresholds.inactive_days} dias sem leitura. Quem entrou há menos de 2 semanas não entra na lista.`}
                    >
                        {atRisk.length === 0 ? (
                            <p className="rounded-2xl border border-dashed p-4 text-sm text-muted-foreground">
                                Ninguém por enquanto. 🙌
                            </p>
                        ) : (
                            <ul className="divide-y rounded-2xl border border-amber-300/60 bg-amber-50/60 dark:border-amber-900 dark:bg-amber-950/30">
                                {atRisk.map((student) => (
                                    <li key={student.id}>
                                        <Link
                                            href={studentPage({
                                                classroom: classroom.slug,
                                                user: student.id,
                                            })}
                                            className="flex flex-col gap-0.5 px-4 py-3 hover:bg-amber-100/60 dark:hover:bg-amber-900/30"
                                        >
                                            <span className="font-medium">
                                                {student.name}
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                {student.reasons.join(' · ')}
                                                {student.last_present &&
                                                    ` · última presença ${student.last_present}`}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>

                    <Section
                        title="Presença por domingo"
                        icon={<CalendarCheck />}
                    >
                        {insights.meetings.length === 0 ? (
                            <EmptyState title="Nenhum encontro realizado ainda" />
                        ) : (
                            <ul className="space-y-2">
                                {insights.meetings.map((meeting) => (
                                    <li
                                        key={meeting.id}
                                        className="rounded-xl border bg-card p-3"
                                    >
                                        <div className="flex items-baseline justify-between gap-3 text-sm">
                                            <span className="min-w-0 truncate">
                                                <span className="font-medium">
                                                    {meeting.date_short}
                                                </span>
                                                {meeting.lesson && (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        · {meeting.lesson}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="shrink-0 text-muted-foreground">
                                                {meeting.taken
                                                    ? `${meeting.present}/${meeting.enrolled}${meeting.visitors ? ` + ${meeting.visitors} visit.` : ''}`
                                                    : 'sem chamada'}
                                            </span>
                                        </div>
                                        {meeting.taken && (
                                            <Bar value={meeting.rate ?? 0} />
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>

                    <Section
                        title="Estudo em casa"
                        icon={<BookOpen />}
                        description="Quantos alunos marcaram leitura durante a semana de cada lição."
                    >
                        {insights.lessons.length === 0 ? (
                            <EmptyState title="Sem dados ainda" />
                        ) : (
                            <ul className="space-y-2">
                                {insights.lessons.map((lesson) => (
                                    <li
                                        key={lesson.id}
                                        className="rounded-xl border bg-card p-3 text-sm"
                                    >
                                        <p className="font-medium">
                                            {lesson.title}
                                        </p>
                                        <p className="mt-0.5 text-muted-foreground">
                                            {lesson.readers} alunos leram (
                                            {lesson.readers_rate}%) · média de{' '}
                                            {lesson.avg_days} dias
                                        </p>
                                        <Bar value={lesson.readers_rate} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>

                    <Section title="Todos os alunos" icon={<Users />}>
                        <ul className="divide-y rounded-2xl border bg-card">
                            {insights.students.map((student) => (
                                <li key={student.id}>
                                    <Link
                                        href={studentPage({
                                            classroom: classroom.slug,
                                            user: student.id,
                                        })}
                                        className="flex items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-muted/50"
                                    >
                                        <span className="font-medium">
                                            {student.name}
                                            {student.is_new && (
                                                <span className="ml-2 rounded-full bg-sky-100 px-2 py-0.5 text-xs text-sky-900 dark:bg-sky-950 dark:text-sky-200">
                                                    novo
                                                </span>
                                            )}
                                        </span>
                                        <span className="text-right text-xs text-muted-foreground">
                                            leitura: {student.last_read ?? '—'}
                                            <br />
                                            presença:{' '}
                                            {student.last_present ?? '—'}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Section>

                    {series.length > 0 && (
                        <Section
                            title="Relatórios do trimestre"
                            icon={<FileText />}
                        >
                            <div className="flex flex-wrap gap-2">
                                {series.map((item) => (
                                    <Button
                                        key={item.id}
                                        asChild
                                        variant="outline"
                                    >
                                        <Link href={report(item.id)}>
                                            <FileText /> {item.title}
                                        </Link>
                                    </Button>
                                ))}
                            </div>
                        </Section>
                    )}
                </div>
            </Page>
        </>
    );
}

function Kpi({
    icon,
    label,
    value,
    tone = 'default',
}: {
    icon: ReactNode;
    label: string;
    value: string;
    tone?: 'default' | 'warning';
}) {
    return (
        <div
            className={cn(
                'rounded-2xl border bg-card p-4',
                tone === 'warning' &&
                    'border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40',
            )}
        >
            <span className="text-primary [&_svg]:size-5">{icon}</span>
            <p className="mt-2 font-serif text-3xl font-semibold">{value}</p>
            <p className="text-xs text-muted-foreground">{label}</p>
        </div>
    );
}

function Bar({ value }: { value: number }) {
    return (
        <div className="mt-2 h-2 rounded-full bg-muted" aria-hidden>
            <div
                className="h-2 rounded-full bg-primary"
                style={{ width: `${Math.min(100, value)}%` }}
            />
        </div>
    );
}
