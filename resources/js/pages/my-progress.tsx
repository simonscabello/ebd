import { Head, Link } from '@inertiajs/react';
import {
    Award,
    BookOpen,
    CalendarCheck,
    Check,
    ChevronRight,
    History,
    Layers,
    Minus,
    X,
} from 'lucide-react';
import { EmptyState, Page, PageHeader, Section } from '@/components/page';
import type {
    AvailableBadge,
    EarnedBadge,
} from '@/components/progress/badge-shelf';
import { BadgeGrid } from '@/components/progress/badge-shelf';
import type { Streak } from '@/components/progress/streak-flame';
import { StreakFlame } from '@/components/progress/streak-flame';
import { show } from '@/routes/lessons';
import { meetingWords } from '@/lib/meeting-words';
import { cn, plural } from '@/lib/utils';
import type { Classroom } from '@/types';

export type LessonProgress = {
    id: number;
    slug: string;
    display_title: string;
    date_short: string | null;
    days_read: number;
    readings_total: number;
    meetings: number;
    present: number;
    /** A lição atual da classe (a semana em curso). */
    current: boolean;
    /** Posição na ordem da série. */
    position: number | null;
    series: {
        id: number;
        title: string;
        current: boolean;
        concluded_on: string | null;
    } | null;
};

export type Progress = {
    streak: Streak;
    badges: EarnedBadge[];
    available_badges: AvailableBadge[];
    lessons: LessonProgress[];
};

type Props = {
    classrooms: Classroom[];
    classroom: Classroom | null;
    progress: Progress | null;
};

export default function MyProgress({ classroom, progress }: Props) {
    return (
        <>
            <Head title="Meu progresso" />
            <Page>
                <PageHeader
                    eyebrow={classroom ? `Classe ${classroom.name}` : undefined}
                    title="Meu progresso"
                    description="Só você vê esta página. Seu professor acompanha a presença e o estudo, nunca as suas anotações."
                />

                {!progress ? (
                    <EmptyState
                        icon={<History />}
                        title="Você ainda não está em uma classe"
                    />
                ) : (
                    <div className="space-y-10">
                        <StreakFlame streak={progress.streak} />

                        <Section title="Selos" icon={<Award />}>
                            <BadgeGrid
                                earned={progress.badges}
                                available={progress.available_badges}
                            />
                        </Section>

                        <Section title="Por série" icon={<Layers />}>
                            <SeriesProgress
                                lessons={progress.lessons}
                                classroom={classroom}
                            />
                        </Section>
                    </div>
                )}
            </Page>
        </>
    );
}

type SeriesGroup = {
    key: string;
    title: string;
    current: boolean;
    concludedOn: string | null;
    lessons: LessonProgress[];
};

/** As lições agrupadas pela série, a atual primeiro; dentro dela, na ordem da série. */
function groupBySeries(lessons: LessonProgress[]): SeriesGroup[] {
    const groups = new Map<string, SeriesGroup>();

    for (const lesson of lessons) {
        const key = lesson.series ? `serie-${lesson.series.id}` : 'avulsas';
        const group = groups.get(key) ?? {
            key,
            title: lesson.series?.title ?? 'Lições avulsas',
            current: lesson.series?.current ?? false,
            concludedOn: lesson.series?.concluded_on ?? null,
            lessons: [],
        };
        group.lessons.push(lesson);
        groups.set(key, group);
    }

    return [...groups.values()]
        .sort((a, b) => Number(b.current) - Number(a.current))
        .map((group) => ({
            ...group,
            lessons: [...group.lessons].sort(
                (a, b) => (a.position ?? 999) - (b.position ?? 999),
            ),
        }));
}

/**
 * "Por série": um cartão por série, a atual aberta e as outras recolhidas.
 * Cada lição mostra a presença na aula e as leituras da semana dela.
 */
function SeriesProgress({
    lessons,
    classroom,
}: {
    lessons: LessonProgress[];
    classroom: Classroom | null;
}) {
    const words = meetingWords(classroom);

    if (lessons.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Nenhuma lição estudada ainda.
            </p>
        );
    }

    return (
        <div className="space-y-3">
            {groupBySeries(lessons).map((group) => {
                const meetings = group.lessons.reduce(
                    (sum, l) => sum + l.meetings,
                    0,
                );
                const present = group.lessons.reduce(
                    (sum, l) => sum + l.present,
                    0,
                );
                const readingsTotal = group.lessons.reduce(
                    (sum, l) => sum + l.readings_total,
                    0,
                );
                const daysRead = group.lessons.reduce(
                    (sum, l) =>
                        sum +
                        (l.readings_total > 0
                            ? Math.min(l.days_read, l.readings_total)
                            : 0),
                    0,
                );
                const summary = [
                    meetings > 0 &&
                        `${present} de ${plural(meetings, words.one, words.many)}`,
                    readingsTotal > 0 &&
                        `${daysRead} de ${plural(readingsTotal, 'leitura', 'leituras')}`,
                ]
                    .filter(Boolean)
                    .join(' · ');

                return (
                    <details
                        key={group.key}
                        open={group.current}
                        className={cn(
                            'group overflow-hidden rounded-2xl border bg-card',
                            group.current && 'border-primary/40',
                        )}
                    >
                        <summary className="flex min-h-14 cursor-pointer list-none items-center gap-3 px-4 py-3 [&::-webkit-details-marker]:hidden">
                            <ChevronRight className="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-90" />
                            <span className="min-w-0 flex-1">
                                <span className="block font-semibold">
                                    {group.title}
                                </span>
                                <span className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                                    {group.current && (
                                        <span className="rounded-md bg-success-soft px-2 py-0.5 font-medium text-success-foreground">
                                            Atual
                                        </span>
                                    )}
                                    {group.concludedOn && (
                                        <span>
                                            Concluída em {group.concludedOn}
                                        </span>
                                    )}
                                    {summary && <span>{summary}</span>}
                                </span>
                            </span>
                        </summary>
                        <div className="flex justify-end gap-3 border-t px-4 pt-2 pb-1 text-[11px] text-muted-foreground">
                            <span className="w-8 text-center">Aula</span>
                            <span className="w-10 text-right">Leitura</span>
                        </div>
                        <ul className="divide-y">
                            {group.lessons.map((lesson) => (
                                <li key={lesson.id}>
                                    <Link
                                        href={show(lesson.slug)}
                                        aria-current={
                                            lesson.current ? 'true' : undefined
                                        }
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
                                                    : 'bg-muted text-muted-foreground',
                                            )}
                                        >
                                            {lesson.position ?? '·'}
                                        </span>
                                        <span
                                            className={cn(
                                                'min-w-0 flex-1 truncate',
                                                lesson.current &&
                                                    'font-semibold',
                                            )}
                                        >
                                            {lesson.display_title}
                                        </span>
                                        <Presence
                                            present={lesson.present}
                                            meetings={lesson.meetings}
                                        />
                                        <span className="w-10 shrink-0 text-right text-xs text-muted-foreground tabular-nums">
                                            {lesson.readings_total > 0
                                                ? `${Math.min(lesson.days_read, lesson.readings_total)}/${lesson.readings_total}`
                                                : '–'}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </details>
                );
            })}
        </div>
    );
}

/** Presença na aula da lição: veio, faltou, parte dos encontros ou ainda não houve chamada. */
function Presence({
    present,
    meetings,
}: {
    present: number;
    meetings: number;
}) {
    const className = 'flex w-8 shrink-0 justify-center';

    if (meetings === 0) {
        return (
            <span className={className} aria-label="Sem chamada ainda">
                <Minus className="size-4 text-muted-foreground" />
            </span>
        );
    }

    if (present === meetings) {
        return (
            <span className={className} aria-label="Presente">
                <Check className="size-4 text-success" />
            </span>
        );
    }

    if (present === 0) {
        return (
            <span className={className} aria-label="Faltou">
                <X className="size-4 text-warning-foreground" />
            </span>
        );
    }

    return (
        <span
            className={cn(
                className,
                'text-xs text-muted-foreground tabular-nums',
            )}
            aria-label={`Presente em ${present} de ${meetings}`}
        >
            {present}/{meetings}
        </span>
    );
}

export function LessonProgressList({
    lessons,
    linkLessons = false,
    classroom,
}: {
    lessons: LessonProgress[];
    linkLessons?: boolean;
    classroom?: Classroom | null;
}) {
    const words = meetingWords(classroom);

    if (lessons.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Nenhuma lição estudada ainda.
            </p>
        );
    }

    return (
        <ul className="divide-y rounded-2xl border bg-card">
            {lessons.map((lesson) => (
                <li key={lesson.id} className="space-y-2 px-4 py-3">
                    <div className="flex items-baseline justify-between gap-3">
                        {linkLessons ? (
                            <Link
                                href={show(lesson.slug)}
                                className="font-medium hover:underline"
                            >
                                {lesson.display_title}
                            </Link>
                        ) : (
                            <span className="font-medium">
                                {lesson.display_title}
                            </span>
                        )}
                        <span className="shrink-0 text-xs text-muted-foreground">
                            {lesson.date_short}
                        </span>
                    </div>
                    <div className="grid gap-1.5 text-xs text-muted-foreground sm:grid-cols-2">
                        <Meter
                            icon={<BookOpen className="size-3.5" />}
                            label={readingLabel(
                                lesson.days_read,
                                lesson.readings_total,
                            )}
                            value={
                                lesson.readings_total > 0
                                    ? lesson.days_read / lesson.readings_total
                                    : Math.min(lesson.days_read, 1)
                            }
                        />
                        {lesson.meetings > 0 && (
                            <Meter
                                icon={<CalendarCheck className="size-3.5" />}
                                label={`Presença: ${lesson.present} de ${plural(lesson.meetings, words.one, words.many)}`}
                                value={lesson.present / lesson.meetings}
                            />
                        )}
                    </div>
                </li>
            ))}
        </ul>
    );
}

/** "Leitura: 3 de 6 dias"; sem plano de leitura, só os dias lidos. */
function readingLabel(read: number, total: number): string {
    if (total === 0) {
        return read > 0
            ? `Leitura: ${plural(read, 'dia', 'dias')}`
            : 'Sem plano de leitura';
    }

    return `Leitura: ${read} de ${plural(total, 'dia', 'dias')}`;
}

function Meter({
    icon,
    label,
    value,
}: {
    icon: React.ReactNode;
    label: string;
    value: number;
}) {
    return (
        <div>
            <p className="mb-1 flex items-center gap-1">
                {icon} {label}
            </p>
            <div className="h-1.5 rounded-full bg-muted">
                <div
                    className="h-1.5 rounded-full bg-primary"
                    style={{
                        width: `${Math.min(100, Math.round(value * 100))}%`,
                    }}
                />
            </div>
        </div>
    );
}
