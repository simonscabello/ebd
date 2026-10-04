import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Award,
    BookMarked,
    CalendarDays,
    CalendarOff,
    FileText,
    Hourglass,
    Layers,
    NotebookPen,
    Presentation,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { InstallAppBanner } from '@/components/install-app-banner';
import { LessonHero } from '@/components/lesson/lesson-hero';
import { ReadToggle } from '@/components/lesson/reading-plan';
import { ReadingSheet } from '@/components/lesson/reading-sheet';
import { TodayReadingCard } from '@/components/lesson/today-reading-card';
import { RemindersBanner } from '@/components/reminders-banner';
import { EmptyState, Page } from '@/components/page';
import { DailyBlocks } from '@/components/week/daily-blocks';
import { TodayReading } from '@/components/week/today-reading';
import { WeekSummary } from '@/components/week/week-summary';
import { useReadingCheckin } from '@/hooks/use-reading-checkin';
import { cn, plural } from '@/lib/utils';
import { home, library, login, myProgress, myWeek } from '@/routes';
import { show, sunday } from '@/routes/lessons';
import type {
    ClassMeeting,
    Classroom,
    Lesson,
    LessonReading,
    Series,
    StudyWeek,
} from '@/types';

type Props = {
    greeting: string;
    today: { date: string; weekday: number; label: string };
    classrooms: Classroom[];
    classroom: Classroom | null;
    isMember: boolean;
    isStudent: boolean;
    nextLesson: Lesson | null;
    /** Semana de estudo (só para quem é da classe e tem lição). */
    week: StudyWeek | null;
    meeting: ClassMeeting | null;
    meetingIndex: number;
    meetingTotal: number;
    preparing: boolean;
    cancelledBefore: ClassMeeting[];
    currentSeries: Series | null;
    recentLessons: Lesson[];
};

export default function Home({
    greeting,
    classrooms,
    classroom,
    isMember,
    nextLesson,
    week,
    meeting,
    meetingIndex,
    meetingTotal,
    preparing,
    cancelledBefore,
    currentSeries,
    recentLessons,
}: Props) {
    const { auth } = usePage().props;
    const name = auth.user?.first_name;

    return (
        <>
            <Head title="Início" />

            <Page>
                <InstallAppBanner />
                {isMember && <RemindersBanner />}
                <header className="mb-6">
                    <h1 className="font-serif text-3xl font-semibold tracking-tight md:text-4xl">
                        {greeting}
                        {name ? `, ${name}` : ''}.
                    </h1>
                    <p className="mt-2 text-lg text-muted-foreground">
                        {headline(meeting)}
                    </p>
                </header>

                {classrooms.length > 1 && classroom && (
                    <nav
                        className="-mx-4 mb-6 flex gap-2 overflow-x-auto px-4 pb-1"
                        aria-label="Escolher classe"
                    >
                        {classrooms.map((item) => (
                            <Link
                                key={item.id}
                                href={home({ query: { classe: item.slug } })}
                                preserveScroll
                                aria-current={
                                    item.id === classroom.id
                                        ? 'true'
                                        : undefined
                                }
                                className={cn(
                                    'shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium transition-colors',
                                    item.id === classroom.id
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'bg-card text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {item.name}
                            </Link>
                        ))}
                    </nav>
                )}

                {currentSeries && (
                    <div className="mb-3 flex items-center gap-2 text-sm text-muted-foreground">
                        <Layers className="size-4 text-primary" />
                        <span>
                            Estamos estudando{' '}
                            <Link
                                href={library({
                                    query: { serie: currentSeries.id },
                                })}
                                className="font-medium text-foreground underline-offset-4 hover:underline"
                            >
                                {currentSeries.title}
                            </Link>
                        </span>
                    </div>
                )}

                {cancelledBefore.map((cancelled) => (
                    <p
                        key={cancelled.id}
                        className="mb-3 flex items-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200"
                    >
                        <CalendarOff className="size-4 shrink-0" />
                        <span className="first-letter:uppercase">
                            {cancelled.date_short}: não teremos EBD
                            {cancelled.title ? ` (${cancelled.title})` : ''}.
                        </span>
                    </p>
                ))}

                {nextLesson ? (
                    <NextLesson
                        lesson={nextLesson}
                        week={week}
                        classroom={classroom}
                        meeting={meeting}
                        position={
                            meetingTotal > 1
                                ? `Encontro ${meetingIndex} de ${meetingTotal}`
                                : null
                        }
                    />
                ) : preparing && meeting ? (
                    <EmptyState
                        icon={<Hourglass />}
                        title="A próxima lição está sendo preparada"
                    >
                        <span className="first-letter:uppercase">
                            {meeting.date_label}
                        </span>
                        . Assim que for publicada, ela aparece aqui.
                    </EmptyState>
                ) : (
                    <EmptyState
                        icon={<CalendarDays />}
                        title="A próxima aula ainda não foi publicada"
                    >
                        Enquanto isso, que tal revisar uma aula anterior?
                    </EmptyState>
                )}

                {recentLessons.length > 0 && (
                    <section className="mt-12">
                        <div className="mb-3 flex items-baseline justify-between">
                            <h2 className="text-lg font-semibold tracking-tight">
                                Aulas anteriores
                            </h2>
                            <Link
                                href={library({
                                    query: classroom
                                        ? { classe: classroom.id }
                                        : {},
                                })}
                                className="text-sm font-medium text-primary"
                            >
                                Ver biblioteca
                            </Link>
                        </div>
                        <ul className="divide-y rounded-2xl border bg-card">
                            {recentLessons.map((lesson) => (
                                <li key={lesson.id}>
                                    <Link
                                        href={show(lesson.slug)}
                                        className="flex items-center justify-between gap-4 px-4 py-3.5 hover:bg-muted/50"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate font-medium">
                                                {lesson.display_title}
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                {lesson.date_short}
                                                {lesson.bible_reference &&
                                                    ` · ${lesson.bible_reference}`}
                                            </span>
                                        </span>
                                        <ArrowRight className="size-4 shrink-0 text-muted-foreground" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {!auth.user && (
                    <p className="mt-10 rounded-2xl bg-muted/70 p-4 text-sm text-muted-foreground">
                        Faz parte de uma classe? Peça ao seu professor o seu
                        link de acesso: com ele você marca as leituras, faz
                        anotações e acompanha seu progresso. Já tem senha?{' '}
                        <Link
                            href={login()}
                            className="font-medium text-primary"
                        >
                            Entre na sua conta
                        </Link>
                        .
                    </p>
                )}

                {auth.user && classroom && !isMember && !auth.user.is_admin && (
                    <p className="mt-10 rounded-2xl bg-muted/70 p-4 text-sm text-muted-foreground">
                        Você ainda não está vinculado(a) à classe{' '}
                        {classroom.name}. Peça ao seu professor para adicionar
                        você.
                    </p>
                )}
            </Page>
        </>
    );
}

function headline(meeting: ClassMeeting | null): string {
    if (!meeting || meeting.days_until < 0) {
        return 'Que bom ter você por aqui.';
    }

    if (meeting.days_until === 0) {
        return 'Hoje é dia de EBD!';
    }

    if (meeting.days_until === 1) {
        return 'Amanhã tem EBD. Vamos nos preparar?';
    }

    return `Faltam ${meeting.days_until} dias para a nossa próxima EBD.`;
}

function NextLesson({
    lesson,
    week,
    classroom,
    meeting,
    position,
}: {
    lesson: Lesson;
    week: StudyWeek | null;
    classroom: Classroom | null;
    meeting: ClassMeeting | null;
    position: string | null;
}) {
    const readings = lesson.readings ?? [];
    const materials = lesson.materials ?? [];
    const primary = materials.find((m) => m.is_primary && m.file);
    const complementary = materials.filter(
        (m) => m !== primary && m.type !== 'reference',
    );
    const todayReading = readings.find((r) => r.is_today);
    const lessonUrl = show.url(lesson.slug);
    const readingsHref = week
        ? myWeek.url({
              query: classroom ? { classe: classroom.slug } : {},
          })
        : `${lessonUrl}#leituras`;

    return (
        <>
            <LessonHero
                lesson={lesson}
                eyebrow={[
                    meeting ? 'Próxima aula' : 'Última aula',
                    meeting?.date_label,
                    position,
                ]
                    .filter(Boolean)
                    .join(' · ')}
            />

            {week ? (
                <StudyToday
                    week={week}
                    lessonSlug={lesson.slug}
                    todayReading={todayReading ?? null}
                    readingsHref={readingsHref}
                />
            ) : (
                todayReading && (
                    <TodayReadingCard
                        reading={todayReading}
                        href={`${lessonUrl}#leituras`}
                    />
                )
            )}

            <h2 className="mt-10 mb-3 text-lg font-semibold tracking-tight">
                Atalhos da lição
            </h2>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <StudyTile
                    href={primary?.file?.open_url ?? lessonUrl}
                    external={!!primary}
                    icon={<FileText />}
                    title="Lição"
                    detail={
                        primary
                            ? `Abrir o ${primary.file?.extension || 'PDF'}`
                            : 'Ler o estudo'
                    }
                />
                <StudyTile
                    href={readingsHref}
                    icon={<CalendarDays />}
                    title="Leituras"
                    detail={
                        week?.progress
                            ? `${week.progress.days_done} de ${week.progress.days_total} lidas`
                            : readings.length
                              ? `${plural(readings.length, 'leitura', 'leituras')} na semana`
                              : 'Texto base'
                    }
                />
                <StudyTile
                    href={`${lessonUrl}#materiais`}
                    icon={<BookMarked />}
                    title="Materiais"
                    detail={
                        complementary.length
                            ? plural(complementary.length, 'item', 'itens')
                            : 'Nenhum ainda'
                    }
                />
                <StudyTile
                    href={sunday.url(lesson.slug)}
                    icon={<Presentation />}
                    title="Modo Domingo"
                    detail="Acompanhar a aula"
                />
                {week && (
                    <>
                        <StudyTile
                            href={`${lessonUrl}#anotacoes`}
                            icon={<NotebookPen />}
                            title="Anotações"
                            detail="Suas anotações"
                        />
                        <StudyTile
                            href={myProgress.url()}
                            icon={<Award />}
                            title="Meu progresso"
                            detail="Sequência e selos"
                        />
                    </>
                )}
            </div>
        </>
    );
}

/**
 * O estudo de hoje para quem é da classe: a leitura do dia (ler e marcar ali
 * mesmo), o resumo da semana e o conteúdo liberado hoje.
 */
function StudyToday({
    week,
    lessonSlug,
    todayReading,
    readingsHref,
}: {
    week: StudyWeek;
    lessonSlug: string;
    todayReading: LessonReading | null;
    readingsHref: string;
}) {
    const checkin = useReadingCheckin(lessonSlug);
    const [reading, setReading] = useState<LessonReading | null>(null);
    const days = week.days ?? [];
    const today = days.find((day) => day.is_today);
    const done = today?.done ?? false;
    const toggle = () =>
        today && checkin.toggle(today.weekday, done, todayReading?.id ?? null);

    return (
        <>
            {todayReading && today && (
                <>
                    <TodayReading
                        reading={todayReading}
                        done={done}
                        pending={checkin.isPending(today.weekday)}
                        onToggle={toggle}
                        onRead={() => setReading(todayReading)}
                    />
                    <ReadingSheet
                        reading={reading}
                        open={reading !== null}
                        onOpenChange={(open) => !open && setReading(null)}
                        footer={
                            <ReadToggle
                                done={done}
                                pending={checkin.isPending(today.weekday)}
                                highlight
                                onClick={toggle}
                                className="w-full justify-center"
                            />
                        }
                    />
                </>
            )}

            {week.progress && days.length > 0 && (
                <WeekSummary
                    days={days}
                    progress={week.progress}
                    streak={week.streak}
                    href={readingsHref}
                />
            )}

            {(week.todayBlocks ?? []).length > 0 && (
                <DailyBlocks blocks={week.todayBlocks ?? []} />
            )}
        </>
    );
}

function StudyTile({
    href,
    icon,
    title,
    detail,
    external = false,
}: {
    href: string;
    icon: ReactNode;
    title: string;
    detail: string;
    external?: boolean;
}) {
    const className =
        'flex min-h-28 flex-col justify-between gap-3 rounded-2xl border bg-card p-4 shadow-xs transition-colors hover:border-primary/40 hover:bg-accent/30';
    const content = (
        <>
            <span className="text-primary [&_svg]:size-6">{icon}</span>
            <span>
                <span className="block leading-tight font-semibold">
                    {title}
                </span>
                <span className="text-sm text-muted-foreground">{detail}</span>
            </span>
        </>
    );

    return external ? (
        <a href={href} target="_blank" rel="noopener" className={className}>
            {content}
        </a>
    ) : (
        <Link href={href} className={className}>
            {content}
        </Link>
    );
}
