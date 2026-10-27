import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Check,
    ChevronLeft,
    ChevronRight,
    Search,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EmptyState, Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { library } from '@/routes';
import { show } from '@/routes/lessons';
import type { Classroom, Lesson, Paginated } from '@/types';

type Filters = {
    q: string;
    classe: number | null;
    serie: number | null;
    ano: number | null;
};

/** Uma série (ou as lições avulsas de uma classe), com as lições na ordem dela. */
type Shelf = {
    key: string;
    id: number | null;
    title: string;
    classroom: string;
    current: boolean;
    concluded_on: string | null;
    lessons: {
        position: number | null;
        slug: string;
        title: string;
        bible_reference: string | null;
        date_short: string | null;
        current: boolean;
        studied: boolean;
    }[];
};

type Props = {
    filters: Filters;
    results: Paginated<Lesson>;
    classrooms: Classroom[];
    /** Sem busca: a biblioteca por série. */
    shelves: Shelf[];
};

export default function LibraryIndex({
    filters,
    results,
    classrooms,
    shelves,
}: Props) {
    const { auth } = usePage().props;
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
            only: ['results', 'filters', 'shelves'],
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

    // Com uma classe só, o filtro de classe nunca muda o resultado: some.
    const showClassroom = classrooms.length > 1 || !!filters.classe;
    const browsing = !filters.q;

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
                    noValidate
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

                    {(showClassroom || filters.serie) && (
                        <nav
                            aria-label="Filtros"
                            className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1"
                        >
                            {showClassroom && (
                                <>
                                    <FilterChip
                                        active={!filters.classe}
                                        onClick={() =>
                                            visit({ classe: null, serie: null })
                                        }
                                    >
                                        Todas
                                    </FilterChip>
                                    {classrooms.map((classroom) => (
                                        <FilterChip
                                            key={classroom.id}
                                            active={
                                                filters.classe === classroom.id
                                            }
                                            onClick={() =>
                                                visit({
                                                    classe: classroom.id,
                                                    serie: null,
                                                })
                                            }
                                        >
                                            {classroom.name}
                                        </FilterChip>
                                    ))}
                                </>
                            )}
                            {filters.serie && (
                                <FilterChip
                                    active
                                    onClick={() => visit({ serie: null })}
                                    label="Ver todas as séries"
                                >
                                    {shelves[0]?.title ?? 'Série'}
                                    <X className="size-3.5" />
                                </FilterChip>
                            )}
                        </nav>
                    )}
                </form>

                {browsing ? (
                    <div className="mt-6">
                        {shelves.length === 0 ? (
                            <EmptyState
                                icon={<BookOpen />}
                                title={
                                    auth.user
                                        ? 'Nenhuma aula ainda'
                                        : 'Nenhum estudo aberto ainda'
                                }
                            >
                                {auth.user
                                    ? 'Assim que o professor publicar, as aulas aparecem aqui.'
                                    : 'Entre com a sua conta para ver as aulas da sua classe.'}
                            </EmptyState>
                        ) : (
                            <Shelves
                                shelves={shelves}
                                focused={!!filters.serie}
                                showClassroom={showClassroom}
                            />
                        )}
                    </div>
                ) : (
                    <>
                        <div className="mt-6 mb-3 flex items-center justify-between text-sm text-muted-foreground">
                            <span
                                aria-live="polite"
                                className="inline-flex items-center gap-1.5"
                            >
                                {searching ? (
                                    <>
                                        <Spinner className="size-3.5" />{' '}
                                        Buscando…
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
                                            {
                                                preserveScroll: true,
                                                replace: true,
                                            },
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
                                !auth.user && !hasFilters ? (
                                    // Visitante só vê os estudos de exemplo, que podem ainda não existir.
                                    <EmptyState
                                        icon={<BookOpen />}
                                        title="Nenhum estudo aberto ainda"
                                    >
                                        Entre com a sua conta para ver as aulas
                                        da sua classe.
                                    </EmptyState>
                                ) : (
                                    <EmptyState
                                        icon={<BookOpen />}
                                        title="Nenhuma aula encontrada"
                                    >
                                        Tente outras palavras ou remova algum
                                        filtro.
                                    </EmptyState>
                                )
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
                                                            {
                                                                lesson.bible_reference
                                                            }
                                                        </span>
                                                    )}
                                                    {lesson.bible_reference &&
                                                        lesson.series &&
                                                        ' · '}
                                                    {lesson.series && (
                                                        <span className="text-muted-foreground">
                                                            {
                                                                lesson.series
                                                                    .title
                                                            }
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
                    </>
                )}
            </Page>
        </>
    );
}

function FilterChip({
    active,
    onClick,
    label,
    children,
}: {
    active: boolean;
    onClick: () => void;
    label?: string;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            aria-label={label}
            className={cn(
                'inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-full border px-4 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                active
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'bg-card text-muted-foreground hover:text-foreground',
            )}
        >
            {children}
        </button>
    );
}

/**
 * A biblioteca sem busca: as séries atuais abertas primeiro, depois as
 * outras e, no fim, as concluídas, recolhidas.
 */
function Shelves({
    shelves,
    focused,
    showClassroom,
}: {
    shelves: Shelf[];
    /** Veio de um link para uma série: ela já abre. */
    focused: boolean;
    showClassroom: boolean;
}) {
    const sections: [string, Shelf[]][] = [
        [
            shelves.filter((s) => s.current).length === 1
                ? 'Série atual'
                : 'Séries atuais',
            shelves.filter((s) => s.current),
        ],
        ['Outras séries', shelves.filter((s) => !s.current && !s.concluded_on)],
        ['Concluídas', shelves.filter((s) => !s.current && s.concluded_on)],
    ];

    return (
        <div className="space-y-6">
            {sections.map(
                ([label, items]) =>
                    items.length > 0 && (
                        <section key={label}>
                            <h2 className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                {label}
                            </h2>
                            <div className="space-y-2">
                                {items.map((shelf) => (
                                    <ShelfCard
                                        key={shelf.key}
                                        shelf={shelf}
                                        open={shelf.current || focused}
                                        showClassroom={showClassroom}
                                    />
                                ))}
                            </div>
                        </section>
                    ),
            )}
        </div>
    );
}

function ShelfCard({
    shelf,
    open,
    showClassroom,
}: {
    shelf: Shelf;
    open: boolean;
    showClassroom: boolean;
}) {
    const current = shelf.lessons.find((lesson) => lesson.current);
    const meta = [
        showClassroom && shelf.classroom,
        current?.position && `lição ${current.position}`,
        !current &&
            (shelf.lessons.length === 1
                ? '1 lição'
                : `${shelf.lessons.length} lições`),
        shelf.concluded_on && `concluída em ${shelf.concluded_on}`,
    ].filter(Boolean);

    return (
        <details
            open={open}
            className={cn(
                'group overflow-hidden rounded-2xl border bg-card',
                shelf.current && 'border-primary/40',
            )}
        >
            <summary className="flex min-h-14 cursor-pointer list-none items-center gap-3 px-4 py-3 [&::-webkit-details-marker]:hidden">
                <ChevronRight className="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-90" />
                <span className="min-w-0 flex-1">
                    <span className="block font-semibold">{shelf.title}</span>
                    <span className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                        {shelf.current && (
                            <span className="rounded-md bg-success-soft px-2 py-0.5 font-medium text-success-foreground">
                                Atual
                            </span>
                        )}
                        <span className="first-letter:uppercase">
                            {meta.join(' · ')}
                        </span>
                    </span>
                </span>
            </summary>
            <ul className="divide-y border-t">
                {shelf.lessons.map((lesson) => (
                    <li key={lesson.slug}>
                        <Link
                            href={show(lesson.slug)}
                            aria-current={lesson.current ? 'true' : undefined}
                            className={cn(
                                'flex min-h-12 items-center gap-3 px-4 py-2 text-sm transition-colors hover:bg-muted/50',
                                lesson.current && 'bg-accent/50',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-7 shrink-0 items-center justify-center rounded-lg text-xs font-semibold',
                                    lesson.current
                                        ? 'bg-primary text-primary-foreground'
                                        : lesson.studied
                                          ? 'bg-success-soft text-success-foreground'
                                          : 'bg-muted text-muted-foreground',
                                )}
                            >
                                {lesson.position ??
                                    (lesson.studied ? (
                                        <Check className="size-3.5" />
                                    ) : (
                                        '·'
                                    ))}
                            </span>
                            <span
                                className={cn(
                                    'min-w-0 flex-1 truncate',
                                    lesson.current && 'font-semibold',
                                )}
                            >
                                {lesson.title}
                            </span>
                            {lesson.current ? (
                                <span className="rounded-md bg-success-soft px-2 py-0.5 text-xs font-medium text-success-foreground">
                                    Agora
                                </span>
                            ) : (
                                <span className="shrink-0 text-xs text-muted-foreground">
                                    {lesson.date_short ??
                                        lesson.bible_reference}
                                </span>
                            )}
                        </Link>
                    </li>
                ))}
            </ul>
        </details>
    );
}
