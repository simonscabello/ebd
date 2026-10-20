import { Head, Link } from '@inertiajs/react';
import { BookOpen, Plus } from 'lucide-react';
import { OwnerTabs } from '@/components/owner/owner-tabs';
import { EmptyState, Page, PageHeader } from '@/components/page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    create as createLesson,
    edit as editLesson,
} from '@/routes/admin/lessons';
import {
    create as createSeries,
    edit as editSeries,
} from '@/routes/admin/series';

type LibraryLesson = { id: number; title: string; status: string };

type LibrarySeries = {
    id: number;
    title: string;
    description: string | null;
    latest_version: number | null;
    published_at: string | null;
    lessons: LibraryLesson[];
};

/**
 * Biblioteca oficial: os estudos que as igrejas copiam. Editar as lições e
 * publicar versões continua pelas telas de sempre; aqui é a porta de entrada.
 */
export default function OwnerLibrary({
    classroom_id,
    series,
    loose,
}: {
    classroom_id: number;
    series: LibrarySeries[];
    loose: LibraryLesson[];
}) {
    return (
        <>
            <Head title="Biblioteca oficial" />
            <Page width="wide">
                <PageHeader
                    title="Biblioteca oficial"
                    description="Seus estudos para todas as igrejas. Elas só veem uma série depois que você publica uma versão; as lições em rascunho não entram."
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link
                                    href={createLesson({
                                        query: { classe: classroom_id },
                                    })}
                                >
                                    <Plus /> Nova lição
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link
                                    href={createSeries({
                                        query: { classe: classroom_id },
                                    })}
                                >
                                    <Plus /> Nova série
                                </Link>
                            </Button>
                        </>
                    }
                />
                <OwnerTabs />

                {series.length === 0 && loose.length === 0 ? (
                    <EmptyState
                        icon={<BookOpen />}
                        title="Nenhum estudo na biblioteca ainda"
                    />
                ) : (
                    <div className="space-y-4">
                        {series.map((item) => {
                            const published = item.lessons.filter(
                                (lesson) => lesson.status === 'published',
                            ).length;

                            return (
                                <section
                                    key={item.id}
                                    className="rounded-2xl border bg-card p-4 sm:p-5"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <h2 className="font-serif text-lg font-semibold">
                                                {item.title}
                                            </h2>
                                            <p className="text-sm text-muted-foreground">
                                                {published} de{' '}
                                                {item.lessons.length}{' '}
                                                {item.lessons.length === 1
                                                    ? 'lição publicada'
                                                    : 'lições publicadas'}{' '}
                                                ·{' '}
                                                {item.latest_version
                                                    ? `versão ${item.latest_version} em ${item.published_at}`
                                                    : 'nenhuma versão publicada (as igrejas ainda não veem)'}
                                            </p>
                                        </div>
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link href={editSeries(item.id)}>
                                                Série e versões
                                            </Link>
                                        </Button>
                                    </div>
                                    <ul className="mt-3 divide-y text-sm">
                                        {item.lessons.map((lesson) => (
                                            <LessonRow
                                                key={lesson.id}
                                                lesson={lesson}
                                            />
                                        ))}
                                    </ul>
                                </section>
                            );
                        })}

                        {loose.length > 0 && (
                            <section className="rounded-2xl border bg-card p-4 sm:p-5">
                                <h2 className="font-serif text-lg font-semibold">
                                    Lições sem série
                                </h2>
                                <ul className="mt-3 divide-y text-sm">
                                    {loose.map((lesson) => (
                                        <LessonRow
                                            key={lesson.id}
                                            lesson={lesson}
                                        />
                                    ))}
                                </ul>
                            </section>
                        )}
                    </div>
                )}
            </Page>
        </>
    );
}

function LessonRow({ lesson }: { lesson: LibraryLesson }) {
    return (
        <li className="flex items-center justify-between gap-3 py-2">
            <Link
                href={editLesson(lesson.id)}
                className="min-w-0 truncate hover:underline"
            >
                {lesson.title}
            </Link>
            <Badge
                variant={
                    lesson.status === 'published' ? 'default' : 'secondary'
                }
            >
                {lesson.status === 'published' ? 'Publicada' : 'Rascunho'}
            </Badge>
        </li>
    );
}
