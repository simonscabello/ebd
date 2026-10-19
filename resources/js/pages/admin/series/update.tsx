import { Head, Link, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Page, PageHeader } from '@/components/page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import { edit, index as seriesIndex } from '@/routes/admin/series';
import { acknowledge } from '@/routes/admin/series/changes';

type DiffLine = { type: 'same' | 'added' | 'removed'; text: string };

type Comparison = {
    from: number;
    to: number;
    notes: string[];
    lessons: {
        number: number | null;
        title: string;
        status: 'added' | 'removed' | 'changed';
        fields: { name: string; label: string; diff: DiffLine[] }[];
    }[];
};

const STATUS: Record<Comparison['lessons'][number]['status'], string> = {
    added: 'Lição nova',
    removed: 'Lição removida',
    changed: 'Alterada',
};

/**
 * O que mudou no estudo oficial desde a versão copiada. Só mostra: ajustar a
 * cópia é manual, e "Marcar como vista" só tira o aviso.
 */
export default function SeriesUpdate({
    series,
    comparison,
}: {
    series: { id: number; title: string };
    comparison: Comparison;
}) {
    return (
        <>
            <Head title={`Versão nova: ${series.title}`} />
            <Page>
                <PageHeader
                    breadcrumbs={[
                        { title: 'Painel', href: dashboard.url() },
                        { title: 'Séries', href: seriesIndex.url() },
                        { title: series.title, href: edit.url(series.id) },
                        { title: 'Versão nova' },
                    ]}
                    title="Versão nova do estudo oficial"
                    description={`A sua cópia veio da versão ${comparison.from}; a mais recente é a ${comparison.to}. Nada foi alterado nas suas lições: ajuste o que quiser na edição de cada uma.`}
                />

                {comparison.notes.length > 0 && (
                    <section className="mb-6 rounded-2xl bg-muted/60 p-4 text-sm">
                        <p className="font-medium">
                            O que mudou, segundo o autor
                        </p>
                        <ul className="mt-1 list-disc pl-5 text-muted-foreground">
                            {comparison.notes.map((note, i) => (
                                <li key={i}>{note}</li>
                            ))}
                        </ul>
                    </section>
                )}

                <div className="space-y-4">
                    {comparison.lessons.map((lesson, i) => (
                        <section
                            key={i}
                            className="rounded-2xl border bg-card p-4 sm:p-5"
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="font-serif text-lg font-semibold">
                                    {lesson.number
                                        ? `Lição ${lesson.number} — ${lesson.title}`
                                        : lesson.title}
                                </h2>
                                <Badge variant="secondary">
                                    {STATUS[lesson.status]}
                                </Badge>
                            </div>
                            {lesson.fields.map((field) => (
                                <div key={field.name} className="mt-4">
                                    <p className="mb-1 text-sm font-medium">
                                        {field.label}
                                    </p>
                                    <div className="overflow-x-auto rounded-xl border text-sm">
                                        {field.diff.map((line, j) => (
                                            <p
                                                key={j}
                                                className={cn(
                                                    'px-3 py-0.5 whitespace-pre-wrap',
                                                    line.type === 'added' &&
                                                        'bg-success-soft',
                                                    line.type === 'removed' &&
                                                        'bg-destructive/10 text-muted-foreground line-through',
                                                )}
                                            >
                                                <span
                                                    aria-hidden
                                                    className="mr-2 inline-block w-3 text-muted-foreground"
                                                >
                                                    {line.type === 'added'
                                                        ? '+'
                                                        : line.type ===
                                                            'removed'
                                                          ? '−'
                                                          : ''}
                                                </span>
                                                {line.text || ' '}
                                            </p>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </section>
                    ))}
                </div>

                <div className="mt-8 flex flex-wrap gap-3">
                    <Button
                        onClick={() => router.post(acknowledge.url(series.id))}
                    >
                        <Check /> Marcar como vista
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={edit(series.id)}>Voltar à série</Link>
                    </Button>
                </div>
            </Page>
        </>
    );
}
