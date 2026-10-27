import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    Ellipsis,
    Library,
    Plus,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import { ClassroomShell } from '@/components/admin/classroom-header';
import type { ClassroomHeaderData } from '@/components/admin/classroom-header';
import {
    LessonMenu,
    LessonNumber,
    LessonStatusBadge,
} from '@/components/admin/lesson-row';
import { Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { dayMonth } from '@/lib/dates';
import { cn } from '@/lib/utils';
import { update as setCurrentSeries } from '@/routes/admin/classrooms/current-series';
import {
    create as createLesson,
    edit as editLesson,
} from '@/routes/admin/lessons';
import { index as library } from '@/routes/admin/library';
import {
    changes,
    conclude,
    create as createSeries,
    edit as editSeries,
    reopen,
} from '@/routes/admin/series';
import type { Classroom, LessonStatus } from '@/types';

type LessonItem = {
    id: number;
    slug: string;
    number: number | null;
    display_title: string;
    status: LessonStatus;
    studied: boolean;
    is_current: boolean;
};

type SeriesGroup = {
    id: number;
    title: string;
    is_current: boolean;
    concluded_on: string | null;
    update_available: { from: number; to: number } | null;
    lessons: LessonItem[];
};

type Props = {
    classroom: Classroom;
    header: ClassroomHeaderData;
    series: SeriesGroup[];
    standalone: LessonItem[];
};

/**
 * Aba Lições da classe: cada série num cartão que abre e fecha. A atual vem
 * primeiro e aberta, as em andamento depois (fechadas) e as concluídas no
 * fim. As lições já estudadas ficam recolhidas dentro da série delas.
 */
export default function ClassroomLessons({
    classroom,
    header,
    series,
    standalone,
}: Props) {
    const [query, setQuery] = useState('');
    const search = query.trim().toLocaleLowerCase('pt-BR');
    const matches = (lesson: LessonItem) =>
        search === '' ||
        lesson.display_title.toLocaleLowerCase('pt-BR').includes(search);

    const visible = series
        .map((group) => ({ ...group, lessons: group.lessons.filter(matches) }))
        .filter((group) => search === '' || group.lessons.length > 0);
    const ongoing = visible.filter((group) => group.concluded_on === null);
    const concluded = visible.filter((group) => group.concluded_on !== null);
    const loose = standalone.filter(matches);

    return (
        <>
            <Head title={`Lições · ${classroom.name}`} />

            <Page width="wide">
                <ClassroomShell
                    classroom={classroom}
                    header={header}
                    active="licoes"
                />

                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative sm:w-72">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Buscar lição"
                            aria-label="Buscar lição"
                            className="pl-9"
                        />
                    </div>
                    <div className="flex flex-wrap gap-2 sm:ml-auto">
                        <Button asChild variant="outline">
                            <Link href={library.url()}>
                                <Library /> Biblioteca oficial
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link
                                href={createSeries.url({
                                    query: { classe: classroom.id },
                                })}
                            >
                                <Plus /> Nova série
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link
                                href={createLesson.url({
                                    query: { classe: classroom.id },
                                })}
                            >
                                <Plus /> Nova lição
                            </Link>
                        </Button>
                    </div>
                </div>

                {visible.length === 0 && loose.length === 0 ? (
                    <p className="rounded-2xl border border-dashed p-6 text-sm text-muted-foreground">
                        {search
                            ? 'Nenhuma lição com esse nome.'
                            : 'Nenhuma série ainda. Crie uma série ou copie um estudo da biblioteca oficial.'}
                    </p>
                ) : (
                    <div className="space-y-3">
                        {ongoing.map((group) => (
                            <SeriesCard
                                key={group.id}
                                classroom={classroom}
                                group={group}
                                searching={search !== ''}
                            />
                        ))}

                        {loose.length > 0 && (
                            <SeriesCard
                                classroom={classroom}
                                group={{
                                    id: 0,
                                    title: 'Lições avulsas',
                                    is_current: false,
                                    concluded_on: null,
                                    update_available: null,
                                    lessons: loose,
                                }}
                                searching={search !== ''}
                                standalone
                            />
                        )}

                        {concluded.length > 0 && (
                            <>
                                <h2 className="px-1 pt-4 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Concluídas
                                </h2>
                                {concluded.map((group) => (
                                    <SeriesCard
                                        key={group.id}
                                        classroom={classroom}
                                        group={group}
                                        searching={search !== ''}
                                    />
                                ))}
                            </>
                        )}
                    </div>
                )}
            </Page>
        </>
    );
}

function storageKey(classroom: Classroom, id: number) {
    return `licoes:${classroom.slug}:${id}`;
}

/** Aberto/fechado lembrado neste aparelho; sem lembrança, só a atual abre. */
function useOpen(classroom: Classroom, group: SeriesGroup) {
    const [open, setOpen] = useState(() => {
        try {
            const saved = window.localStorage.getItem(
                storageKey(classroom, group.id),
            );

            return saved === null ? group.is_current : saved === '1';
        } catch {
            return group.is_current;
        }
    });

    const toggle = () => {
        setOpen(!open);

        try {
            window.localStorage.setItem(
                storageKey(classroom, group.id),
                open ? '0' : '1',
            );
        } catch {
            // Sem armazenamento (aba anônima): só não lembra.
        }
    };

    return [open, toggle] as const;
}

function SeriesCard({
    classroom,
    group,
    searching,
    standalone = false,
}: {
    classroom: Classroom;
    group: SeriesGroup;
    searching: boolean;
    standalone?: boolean;
}) {
    const [open, toggle] = useOpen(classroom, group);
    const [showStudied, setShowStudied] = useState(false);
    const expanded = open || searching;
    const studied = group.lessons.filter((lesson) => lesson.studied);
    const shown = group.lessons.filter(
        (lesson) =>
            !lesson.studied || lesson.is_current || showStudied || searching,
    );
    const hiddenStudied =
        studied.length - studied.filter((l) => shown.includes(l)).length;
    const concluded = group.concluded_on !== null;
    const total = group.lessons.length;

    return (
        <section
            className={cn(
                'overflow-hidden rounded-2xl border bg-card',
                group.is_current && 'border-primary/40',
                concluded && 'opacity-80',
            )}
        >
            <div className="flex items-center gap-3 px-4 py-3">
                <button
                    type="button"
                    onClick={toggle}
                    aria-expanded={expanded}
                    className="flex min-h-11 min-w-0 flex-1 items-center gap-3 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {expanded ? (
                        <ChevronDown className="size-4 shrink-0 text-muted-foreground" />
                    ) : (
                        <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                    )}
                    <span className="min-w-0 flex-1">
                        <span className="flex flex-wrap items-center gap-2">
                            <span className="font-semibold">{group.title}</span>
                            {group.is_current && (
                                <span className="rounded-md bg-primary/15 px-2 py-0.5 text-xs font-medium text-primary">
                                    Série atual
                                </span>
                            )}
                            {concluded && (
                                <span className="rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                    Concluída em {dayMonth(group.concluded_on!)}
                                </span>
                            )}
                        </span>
                        {!standalone && total > 0 && (
                            <span className="mt-1.5 flex items-center gap-2 text-xs text-muted-foreground">
                                <span
                                    aria-hidden="true"
                                    className="h-1.5 w-28 overflow-hidden rounded-full bg-muted"
                                >
                                    <span
                                        className="block h-full rounded-full bg-primary"
                                        style={{
                                            width: `${Math.round((studied.length / total) * 100)}%`,
                                        }}
                                    />
                                </span>
                                {studied.length} de {total} estudadas
                            </span>
                        )}
                        {standalone && (
                            <span className="mt-1 block text-xs text-muted-foreground">
                                {total} {total === 1 ? 'lição' : 'lições'} sem
                                série
                            </span>
                        )}
                    </span>
                </button>
                {!standalone && (
                    <SeriesMenu classroom={classroom} group={group} />
                )}
            </div>

            {expanded && (
                <ul className="divide-y border-t">
                    {hiddenStudied > 0 && (
                        <li>
                            <button
                                type="button"
                                onClick={() => setShowStudied(true)}
                                className="flex min-h-12 w-full items-center gap-3 px-4 text-left text-sm text-muted-foreground hover:bg-muted/40"
                            >
                                <LessonNumber number={null} done />
                                <span className="flex-1">
                                    {hiddenStudied}{' '}
                                    {hiddenStudied === 1
                                        ? 'lição estudada'
                                        : 'lições estudadas'}
                                </span>
                                Mostrar
                            </button>
                        </li>
                    )}
                    {shown.map((lesson) => (
                        <li
                            key={lesson.id}
                            className={cn(
                                'flex items-center gap-3 px-4 py-2.5',
                                lesson.is_current && 'bg-primary/5',
                            )}
                        >
                            <LessonNumber
                                number={lesson.number}
                                done={lesson.studied && !lesson.is_current}
                            />
                            <Link
                                href={editLesson.url(lesson.id)}
                                className={cn(
                                    'min-w-0 flex-1 truncate hover:underline',
                                    lesson.studied &&
                                        !lesson.is_current &&
                                        'text-muted-foreground',
                                )}
                            >
                                {lesson.display_title}
                            </Link>
                            {lesson.is_current ? (
                                <span className="rounded-md bg-primary/15 px-2 py-0.5 text-xs font-medium text-primary">
                                    Em estudo
                                </span>
                            ) : lesson.studied ? (
                                <span className="text-xs text-muted-foreground">
                                    Estudada
                                </span>
                            ) : (
                                <LessonStatusBadge status={lesson.status} />
                            )}
                            <LessonMenu
                                classroom={classroom}
                                lesson={lesson}
                                studyNow={!lesson.is_current}
                            />
                        </li>
                    ))}
                    {total === 0 && (
                        <li className="px-4 py-3 text-sm text-muted-foreground">
                            Nenhuma lição ainda.{' '}
                            <Link
                                href={createLesson.url({
                                    query: {
                                        classe: classroom.id,
                                        serie: group.id,
                                    },
                                })}
                                className="text-primary hover:underline"
                            >
                                Escrever a primeira
                            </Link>
                        </li>
                    )}
                </ul>
            )}
        </section>
    );
}

/** ⋯ da série: tornar atual, concluir/reabrir, nova lição, editar. */
function SeriesMenu({
    classroom,
    group,
}: {
    classroom: Classroom;
    group: SeriesGroup;
}) {
    const post = (url: string, data: Record<string, number> = {}) =>
        router.post(url, data, { preserveScroll: true });

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Mais da série ${group.title}`}
                >
                    <Ellipsis />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {!group.is_current && (
                    <DropdownMenuItem
                        onSelect={() =>
                            post(setCurrentSeries.url(classroom.slug), {
                                series_id: group.id,
                            })
                        }
                    >
                        Tornar série atual
                    </DropdownMenuItem>
                )}
                {group.concluded_on === null ? (
                    <DropdownMenuItem
                        onSelect={() => post(conclude.url(group.id))}
                    >
                        Marcar como concluída
                    </DropdownMenuItem>
                ) : (
                    <DropdownMenuItem
                        onSelect={() => post(reopen.url(group.id))}
                    >
                        Reabrir
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem asChild>
                    <Link
                        href={createLesson.url({
                            query: { classe: classroom.id, serie: group.id },
                        })}
                    >
                        Nova lição nesta série
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href={editSeries.url(group.id)}>Editar série</Link>
                </DropdownMenuItem>
                {group.update_available && (
                    <DropdownMenuItem asChild>
                        <Link href={changes.url(group.id)}>
                            Ver atualização da biblioteca
                        </Link>
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
