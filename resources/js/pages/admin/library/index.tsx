import { Head, Link } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { EmptyState, Page, PageHeader } from '@/components/page';
import { dashboard } from '@/routes/admin';
import { show } from '@/routes/admin/library';
import { index as seriesIndex } from '@/routes/admin/series';

type Study = {
    id: number;
    title: string;
    description: string | null;
    lessons_count: number;
    version: number;
    published_at: string;
};

/**
 * Biblioteca oficial: estudos prontos, livres para usar numa turma.
 */
export default function LibraryIndex({ studies }: { studies: Study[] }) {
    return (
        <>
            <Head title="Biblioteca oficial" />
            <Page width="wide">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Painel', href: dashboard.url() },
                        { title: 'Séries', href: seriesIndex.url() },
                        { title: 'Biblioteca oficial' },
                    ]}
                    title="Biblioteca oficial"
                    description="Estudos prontos e gratuitos. Use numa turma: ela recebe uma cópia que vocês editam à vontade."
                />

                {studies.length === 0 ? (
                    <EmptyState
                        icon={<BookOpen />}
                        title="Nenhum estudo publicado ainda"
                    />
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2">
                        {studies.map((study) => (
                            <li
                                key={study.id}
                                className="flex flex-col rounded-2xl border bg-card p-4"
                            >
                                <Link
                                    href={show(study.id)}
                                    className="font-serif text-lg font-semibold hover:underline"
                                >
                                    {study.title}
                                </Link>
                                {study.description && (
                                    <p className="mt-1 line-clamp-3 text-sm text-muted-foreground">
                                        {study.description}
                                    </p>
                                )}
                                <p className="mt-auto pt-3 text-sm text-muted-foreground">
                                    {study.lessons_count}{' '}
                                    {study.lessons_count === 1
                                        ? 'lição'
                                        : 'lições'}{' '}
                                    · versão {study.version} ·{' '}
                                    {study.published_at}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </Page>
        </>
    );
}
