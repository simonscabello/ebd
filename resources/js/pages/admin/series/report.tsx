import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import type { Classroom } from '@/types';

type Report = {
    series: {
        id: number;
        title: string;
        starts_on: string | null;
        ends_on: string | null;
    };
    totals: {
        lessons: number;
        taught: number;
        meetings_with_attendance: number;
        review_total: number;
        students: number;
    };
    lessons: {
        id: number;
        title: string;
        dates: string[];
        present: number[];
        readers_rate: number;
    }[];
    students: {
        id: number;
        name: string;
        present: number;
        lessons_studied: number;
        review_answered: number;
        badges: string[];
    }[];
};

const badgeEmoji: Record<string, string> = {
    faithful_reader: '🏅',
    review_master: '🎯',
    perfect_attendance: '⛪',
};

/**
 * Relatório do trimestre. Pensado para imprimir (ou salvar em PDF pelo navegador).
 */
export default function SeriesReport({
    classroom,
    report,
}: {
    classroom: Classroom;
    report: Report;
}) {
    const { totals } = report;

    return (
        <>
            <Head title={`Relatório · ${report.series.title}`} />
            <Page
                width="wide"
                className="print:max-w-none print:px-0 print:pt-0"
            >
                <header className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Classe {classroom.name}
                        </p>
                        <h1 className="font-serif text-3xl font-semibold tracking-tight">
                            {report.series.title}
                        </h1>
                        <p className="text-muted-foreground">
                            {report.series.starts_on} a {report.series.ends_on}{' '}
                            · {totals.taught} de {totals.lessons} lições dadas ·{' '}
                            {totals.students} alunos
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => window.print()}
                        className="print:hidden"
                    >
                        <Printer /> Imprimir
                    </Button>
                </header>

                <h2 className="mb-2 text-lg font-semibold">Lições</h2>
                <div className="mb-8 overflow-x-auto rounded-2xl border bg-card print:border-0">
                    <table className="w-full text-sm">
                        <thead className="text-left text-xs text-muted-foreground uppercase">
                            <tr className="border-b">
                                <th className="p-3">Lição</th>
                                <th className="p-3">Domingos</th>
                                <th className="p-3">Presentes</th>
                                <th className="p-3">Estudaram em casa</th>
                            </tr>
                        </thead>
                        <tbody>
                            {report.lessons.map((lesson) => (
                                <tr
                                    key={lesson.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="p-3 font-medium">
                                        {lesson.title}
                                    </td>
                                    <td className="p-3">
                                        {lesson.dates.join(', ') || '—'}
                                    </td>
                                    <td className="p-3">
                                        {lesson.present.join(' / ') || '—'}
                                    </td>
                                    <td className="p-3">
                                        {lesson.readers_rate}%
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <h2 className="mb-2 text-lg font-semibold">Alunos</h2>
                <div className="overflow-x-auto rounded-2xl border bg-card print:border-0">
                    <table className="w-full text-sm">
                        <thead className="text-left text-xs text-muted-foreground uppercase">
                            <tr className="border-b">
                                <th className="p-3">Aluno</th>
                                <th className="p-3">Presença</th>
                                <th className="p-3">Lições estudadas</th>
                                <th className="p-3">Revisão</th>
                                <th className="p-3">Selos</th>
                            </tr>
                        </thead>
                        <tbody>
                            {report.students.map((student) => (
                                <tr
                                    key={student.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="p-3 font-medium">
                                        {student.name}
                                    </td>
                                    <td className="p-3">
                                        {student.present}/
                                        {totals.meetings_with_attendance}
                                    </td>
                                    <td className="p-3">
                                        {student.lessons_studied}/
                                        {totals.taught}
                                    </td>
                                    <td className="p-3">
                                        {student.review_answered}/
                                        {totals.review_total}
                                    </td>
                                    <td className="p-3">
                                        {student.badges
                                            .map((b) => badgeEmoji[b] ?? '')
                                            .join(' ')}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Page>
        </>
    );
}
