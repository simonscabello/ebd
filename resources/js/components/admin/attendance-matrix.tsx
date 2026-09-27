import { Check } from 'lucide-react';
import { shortDate } from '@/lib/dates';
import { cn } from '@/lib/utils';
import type { SundaySummary } from '@/types';

export type MatrixSunday = SundaySummary & {
    id: number;
    held_on: string;
    lesson: string | null;
    title: string | null;
};

export type MatrixStudent = {
    id: number;
    name: string;
    /** true presente, false faltou, null ainda não fazia parte da classe. */
    cells: (boolean | null)[];
    present: number;
    expected: number;
    rate: number | null;
};

/**
 * Chamada do período: um aluno por linha, um domingo por coluna. A primeira
 * coluna fica fixa e a tabela rola para o lado no celular.
 */
export function AttendanceMatrix({
    sundays,
    students,
}: {
    sundays: MatrixSunday[];
    students: MatrixStudent[];
}) {
    return (
        <div className="overflow-x-auto rounded-2xl border bg-card print:overflow-visible print:rounded-none print:border-0">
            <table className="w-full border-collapse text-sm print:text-xs">
                <caption className="sr-only">
                    Chamada por aluno e por domingo: presente, faltou ou ainda
                    não fazia parte da classe.
                </caption>
                <thead>
                    <tr className="border-b text-xs text-muted-foreground">
                        <th
                            scope="col"
                            className="sticky left-0 z-10 bg-card px-3 py-2 text-left font-medium"
                        >
                            Aluno
                        </th>
                        {sundays.map((sunday) => (
                            <th
                                key={sunday.id}
                                scope="col"
                                className="px-1.5 py-2 text-center font-medium tabular-nums"
                                title={
                                    sunday.lesson ?? sunday.title ?? undefined
                                }
                            >
                                {shortDate(sunday.held_on)}
                            </th>
                        ))}
                        <th
                            scope="col"
                            className="px-3 py-2 text-right font-medium"
                        >
                            Presenças
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {students.map((student) => (
                        <tr
                            key={student.id}
                            className="border-b last:border-b-0"
                        >
                            <th
                                scope="row"
                                className="sticky left-0 z-10 max-w-40 truncate bg-card px-3 py-2 text-left font-medium"
                            >
                                {student.name}
                            </th>
                            {student.cells.map((cell, index) => (
                                <td
                                    key={sundays[index]?.id ?? index}
                                    className="px-1.5 py-2 text-center"
                                >
                                    <Cell value={cell} />
                                </td>
                            ))}
                            <td className="px-3 py-2 text-right whitespace-nowrap tabular-nums">
                                {student.present}/{student.expected}
                                {student.rate !== null && (
                                    <span
                                        className={cn(
                                            'ml-1.5 text-xs',
                                            student.rate < 50
                                                ? 'font-semibold text-warning-foreground'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {student.rate}%
                                    </span>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="border-t bg-muted/40 text-xs text-muted-foreground">
                        <th
                            scope="row"
                            className="sticky left-0 z-10 bg-muted px-3 py-2 text-left font-medium"
                        >
                            Presentes
                        </th>
                        {sundays.map((sunday) => (
                            <td
                                key={sunday.id}
                                className="px-1.5 py-2 text-center tabular-nums"
                            >
                                {sunday.present}
                            </td>
                        ))}
                        <td className="px-3 py-2" />
                    </tr>
                    <tr className="bg-muted/40 text-xs text-muted-foreground">
                        <th
                            scope="row"
                            className="sticky left-0 z-10 bg-muted px-3 py-2 text-left font-medium"
                        >
                            Visitantes
                        </th>
                        {sundays.map((sunday) => (
                            <td
                                key={sunday.id}
                                className="px-1.5 py-2 text-center tabular-nums"
                            >
                                {sunday.visitors || ''}
                            </td>
                        ))}
                        <td className="px-3 py-2" />
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

function Cell({ value }: { value: boolean | null }) {
    if (value === null) {
        return <span className="sr-only">ainda não era da classe</span>;
    }

    return value ? (
        <span className="inline-flex size-6 items-center justify-center rounded-full bg-success-soft text-success-foreground">
            <Check className="size-3.5" aria-label="presente" />
        </span>
    ) : (
        <span
            className="inline-flex size-6 items-center justify-center text-muted-foreground/60"
            aria-label="faltou"
        >
            ·
        </span>
    );
}
