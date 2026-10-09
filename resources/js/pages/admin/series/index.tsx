import { Head, Link } from '@inertiajs/react';
import { Layers, Plus } from 'lucide-react';
import { EmptyState, Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { report } from '@/routes/admin/classrooms';
import { create as createLesson } from '@/routes/admin/lessons';
import { create, edit } from '@/routes/admin/series';
import type { Series } from '@/types';

export default function SeriesIndex({ series }: { series: Series[] }) {
    return (
        <>
            <Head title="Séries" />
            <Page width="wide">
                <PageHeader
                    title="Séries de estudo"
                    description="Cada série agrupa as lições de uma revista ou tema."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> Nova série
                            </Link>
                        </Button>
                    }
                />

                {series.length === 0 ? (
                    <EmptyState
                        icon={<Layers />}
                        title="Nenhuma série cadastrada"
                    />
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2">
                        {series.map((item) => (
                            <li
                                key={item.id}
                                className="flex flex-col rounded-2xl border bg-card p-4"
                            >
                                <p className="text-sm text-muted-foreground">
                                    {item.classroom?.name}
                                </p>
                                <Link
                                    href={edit(item.id)}
                                    className="mt-0.5 font-serif text-lg font-semibold hover:underline"
                                >
                                    {item.title}
                                </Link>
                                {item.description && (
                                    <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                        {item.description}
                                    </p>
                                )}
                                <div className="mt-auto flex items-center justify-between pt-3 text-sm">
                                    <span className="text-muted-foreground">
                                        {item.lessons_count ?? 0}{' '}
                                        {item.lessons_count === 1
                                            ? 'lição'
                                            : 'lições'}
                                    </span>
                                    <span className="flex gap-2">
                                        {item.classroom && (
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={report(
                                                        {
                                                            classroom:
                                                                item.classroom
                                                                    .slug,
                                                        },
                                                        {
                                                            query: {
                                                                serie: item.id,
                                                            },
                                                        },
                                                    )}
                                                >
                                                    Relatório
                                                </Link>
                                            </Button>
                                        )}
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                href={createLesson({
                                                    query: {
                                                        classe: item.classroom_id,
                                                        serie: item.id,
                                                    },
                                                })}
                                            >
                                                <Plus /> Lição
                                            </Link>
                                        </Button>
                                    </span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Page>
        </>
    );
}
