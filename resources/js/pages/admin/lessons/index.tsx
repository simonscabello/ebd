import { Head, Link, router } from '@inertiajs/react';
import {
    BookOpen,
    ChevronLeft,
    ChevronRight,
    Plus,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import { StatusBadge } from '@/components/admin/status-badge';
import { EmptyState, Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { create, edit, index } from '@/routes/admin/lessons';
import type { Classroom, Lesson, Option, Paginated } from '@/types';

type Filters = { classe: number | null; status: string | null; q: string };

type Props = {
    lessons: Paginated<Lesson>;
    filters: Filters;
    classrooms: Classroom[];
    statuses: Option[];
};

export default function AdminLessonsIndex({
    lessons,
    filters,
    classrooms,
    statuses,
}: Props) {
    const [query, setQuery] = useState(filters.q);

    const visit = (changes: Partial<Filters>) => {
        const next = { ...filters, q: query, ...changes };
        router.get(
            index.url(),
            Object.fromEntries(
                Object.entries(next).filter(
                    ([, value]) => value !== null && value !== '',
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Lições" />

            <Page width="wide">
                <PageHeader
                    title="Lições"
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> Nova lição
                            </Link>
                        </Button>
                    }
                />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        visit({});
                    }}
                    className="mb-5 grid gap-2 sm:grid-cols-[1fr_auto_auto]"
                >
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            onBlur={() => query !== filters.q && visit({})}
                            placeholder="Buscar pelo título"
                            className="pl-9"
                            aria-label="Buscar pelo título"
                        />
                    </div>
                    {classrooms.length > 1 && (
                        <NativeSelect
                            aria-label="Classe"
                            value={filters.classe ?? ''}
                            onChange={(event) =>
                                visit({
                                    classe: event.target.value
                                        ? Number(event.target.value)
                                        : null,
                                })
                            }
                        >
                            <option value="">Todas as classes</option>
                            {classrooms.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </NativeSelect>
                    )}
                    <NativeSelect
                        aria-label="Situação"
                        value={filters.status ?? ''}
                        onChange={(event) =>
                            visit({ status: event.target.value || null })
                        }
                    >
                        <option value="">Todas as situações</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </NativeSelect>
                </form>

                {lessons.data.length === 0 ? (
                    <EmptyState
                        icon={<BookOpen />}
                        title="Nenhuma lição encontrada"
                    />
                ) : (
                    <ul className="divide-y rounded-2xl border bg-card">
                        {lessons.data.map((lesson) => (
                            <li key={lesson.id}>
                                <Link
                                    href={edit(lesson.id)}
                                    className="flex items-center gap-4 px-4 py-3.5 hover:bg-muted/50"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">
                                            {lesson.title}
                                        </p>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {[
                                                lesson.date_short ?? 'Sem data',
                                                lesson.classroom?.name,
                                                lesson.series?.title,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                    <StatusBadge
                                        status={lesson.status}
                                        label={lesson.status_label}
                                    />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                {lessons.meta.last_page > 1 && (
                    <nav className="mt-5 flex items-center justify-between">
                        <Button
                            variant="outline"
                            disabled={!lessons.links.prev}
                            onClick={() =>
                                lessons.links.prev &&
                                router.get(lessons.links.prev)
                            }
                        >
                            <ChevronLeft /> Anterior
                        </Button>
                        <span className="text-sm text-muted-foreground">
                            {lessons.meta.current_page} /{' '}
                            {lessons.meta.last_page}
                        </span>
                        <Button
                            variant="outline"
                            disabled={!lessons.links.next}
                            onClick={() =>
                                lessons.links.next &&
                                router.get(lessons.links.next)
                            }
                        >
                            Próxima <ChevronRight />
                        </Button>
                    </nav>
                )}
            </Page>
        </>
    );
}
