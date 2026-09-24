import { Head, Link, router } from '@inertiajs/react';
import { BookOpen, ChevronLeft, ChevronRight, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EmptyState, Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { library } from '@/routes';
import { show } from '@/routes/lessons';
import type { Classroom, Lesson, Paginated, Series } from '@/types';

type Filters = {
    q: string;
    classe: number | null;
    serie: number | null;
    ano: number | null;
};

type Props = {
    filters: Filters;
    results: Paginated<Lesson>;
    classrooms: Classroom[];
    series: Series[];
    years: number[];
};

export default function LibraryIndex({
    filters,
    results,
    classrooms,
    series,
    years,
}: Props) {
    const [query, setQuery] = useState(filters.q);
    const [searching, setSearching] = useState(false);
    const firstRender = useRef(true);

    const visit = (changes: Partial<Filters>) => {
        const next = { ...filters, q: query, ...changes };
        const params: Record<string, string | number> = {};

        if (next.q) params.q = next.q;
        if (next.classe) params.classe = next.classe;
        if (next.serie) params.serie = next.serie;
        if (next.ano) params.ano = next.ano;

        router.get(library.url(), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['results', 'filters', 'series'],
            onStart: () => setSearching(true),
            onFinish: () => setSearching(false),
        });
    };

    // Busca enquanto digita, com um pequeno atraso.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => visit({ q: query }), 350);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    const hasFilters = !!(
        filters.q ||
        filters.classe ||
        filters.serie ||
        filters.ano
    );

    return (
        <>
            <Head title="Biblioteca" />

            <Page>
                <PageHeader
                    title="Biblioteca"
                    description="Todas as aulas da EBD, guardadas para consultar quando quiser."
                />

                <form
                    role="search"
                    onSubmit={(event) => {
                        event.preventDefault();
                        visit({ q: query });
                    }}
                    className="space-y-3"
                >
                    <label htmlFor="busca" className="sr-only">
                        Buscar
                    </label>
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="busca"
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Título, assunto, série ou texto bíblico"
                            className="h-12 rounded-xl pl-11 text-base md:h-12"
                            autoComplete="off"
                            enterKeyHint="search"
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <NativeSelect
                            aria-label="Classe"
                            value={filters.classe ?? ''}
                            onChange={(event) =>
                                visit({
                                    classe: event.target.value
                                        ? Number(event.target.value)
                                        : null,
                                    serie: null,
                                })
                            }
                        >
                            <option value="">Classe: todas</option>
                            {classrooms.map((classroom) => (
                                <option key={classroom.id} value={classroom.id}>
                                    {classroom.name}
                                </option>
                            ))}
                        </NativeSelect>
                        <NativeSelect
                            aria-label="Série"
                            className="order-first col-span-2 sm:order-none sm:col-span-1"
                            value={filters.serie ?? ''}
                            onChange={(event) =>
                                visit({
                                    serie: event.target.value
                                        ? Number(event.target.value)
                                        : null,
                                })
                            }
                        >
                            <option value="">Série: todas</option>
                            {series.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.title}
                                </option>
                            ))}
                        </NativeSelect>
                        <NativeSelect
                            aria-label="Ano"
                            value={filters.ano ?? ''}
                            onChange={(event) =>
                                visit({
                                    ano: event.target.value
                                        ? Number(event.target.value)
                                        : null,
                                })
                            }
                        >
                            <option value="">Ano: todos</option>
                            {years.map((year) => (
                                <option key={year} value={year}>
                                    {year}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                </form>

                <div className="mt-6 mb-3 flex items-center justify-between text-sm text-muted-foreground">
                    <span
                        aria-live="polite"
                        className="inline-flex items-center gap-1.5"
                    >
                        {searching ? (
                            <>
                                <Spinner className="size-3.5" /> Buscando…
                            </>
                        ) : results.meta.total === 1 ? (
                            '1 aula'
                        ) : (
                            `${results.meta.total} aulas`
                        )}
                    </span>
                    {hasFilters && (
                        <button
                            type="button"
                            onClick={() => {
                                setQuery('');
                                router.get(
                                    library.url(),
                                    {},
                                    { preserveScroll: true, replace: true },
                                );
                            }}
                            className="inline-flex min-h-9 items-center gap-1 rounded-md font-medium text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <X className="size-4" /> Limpar filtros
                        </button>
                    )}
                </div>

                <div
                    aria-busy={searching || undefined}
                    className={cn(
                        'transition-opacity',
                        searching && 'opacity-60',
                    )}
                >
                    {results.data.length === 0 ? (
                        <EmptyState
                            icon={<BookOpen />}
                            title="Nenhuma aula encontrada"
                        >
                            Tente outras palavras ou remova algum filtro.
                        </EmptyState>
                    ) : (
                        <ul className="space-y-3">
                            {results.data.map((lesson) => (
                                <li key={lesson.id}>
                                    <Link
                                        href={show(lesson.slug)}
                                        className="block rounded-2xl border bg-card p-4 shadow-xs transition-colors hover:border-primary/40 hover:bg-accent/20"
                                    >
                                        <p className="text-sm text-muted-foreground">
                                            {[
                                                lesson.date_short,
                                                lesson.classroom?.name,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                        <h2 className="mt-0.5 font-serif text-xl font-semibold tracking-tight text-balance">
                                            {lesson.title}
                                        </h2>
                                        <p className="mt-1 text-sm">
                                            {lesson.bible_reference && (
                                                <span className="font-medium">
                                                    {lesson.bible_reference}
                                                </span>
                                            )}
                                            {lesson.bible_reference &&
                                                lesson.series &&
                                                ' · '}
                                            {lesson.series && (
                                                <span className="text-muted-foreground">
                                                    {lesson.series.title}
                                                </span>
                                            )}
                                        </p>
                                        {lesson.headline ? (
                                            <p
                                                className="mt-2 text-sm text-muted-foreground [&_mark]:rounded [&_mark]:bg-highlight [&_mark]:px-0.5 [&_mark]:text-highlight-foreground"
                                                // Trecho escapado no servidor; só <mark> é inserido.
                                                dangerouslySetInnerHTML={{
                                                    __html: `…${lesson.headline}…`,
                                                }}
                                            />
                                        ) : (
                                            lesson.summary && (
                                                <p className="mt-2 line-clamp-2 text-sm text-muted-foreground">
                                                    {lesson.summary}
                                                </p>
                                            )
                                        )}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {results.meta.last_page > 1 && (
                    <nav
                        className="mt-6 flex items-center justify-between"
                        aria-label="Paginação"
                    >
                        <Button
                            asChild
                            variant="outline"
                            disabled={!results.links.prev}
                        >
                            {results.links.prev ? (
                                <Link
                                    href={results.links.prev}
                                    preserveScroll={false}
                                >
                                    <ChevronLeft /> Anteriores
                                </Link>
                            ) : (
                                <span
                                    aria-disabled
                                    className="pointer-events-none opacity-50"
                                >
                                    <ChevronLeft /> Anteriores
                                </span>
                            )}
                        </Button>
                        <span className="text-sm text-muted-foreground">
                            Página {results.meta.current_page} de{' '}
                            {results.meta.last_page}
                        </span>
                        <Button asChild variant="outline">
                            {results.links.next ? (
                                <Link href={results.links.next}>
                                    Próximas <ChevronRight />
                                </Link>
                            ) : (
                                <span
                                    aria-disabled
                                    className="pointer-events-none opacity-50"
                                >
                                    Próximas <ChevronRight />
                                </span>
                            )}
                        </Button>
                    </nav>
                )}
            </Page>
        </>
    );
}
