import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BookMarked,
    BookOpen,
    CalendarDays,
    CalendarOff,
    FileText,
    HelpCircle,
    Hourglass,
    KeyRound,
    Layers,
    Sparkles,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { ShareButton } from '@/components/lesson/share-button';
import { EmptyState, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { home, library, login, myWeek } from '@/routes';
import { show } from '@/routes/lessons';
import type { ClassMeeting, Classroom, Lesson, Series } from '@/types';

type Props = {
    greeting: string;
    today: { date: string; weekday: number; label: string };
    classrooms: Classroom[];
    classroom: Classroom | null;
    isMember: boolean;
    isStudent: boolean;
    nextLesson: Lesson | null;
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
    isStudent,
    nextLesson,
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

                {isStudent && nextLesson && (
                    <Link
                        href={myWeek({
                            query: classroom ? { classe: classroom.slug } : {},
                        })}
                        className="mb-4 flex items-center justify-between gap-3 rounded-2xl border border-primary/30 bg-accent/60 px-4 py-3 font-medium text-accent-foreground hover:bg-accent"
                    >
                        <span>
                            Minha semana: leitura de hoje, curiosidade do dia e
                            preparação para domingo
                        </span>
                        <ArrowRight className="size-4 shrink-0" />
                    </Link>
                )}

                {nextLesson ? (
                    <NextLesson
                        lesson={nextLesson}
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
                        link pessoal: com ele você marca as leituras, faz a
                        revisão e acompanha seu progresso. Já tem senha?{' '}
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
    meeting,
    position,
}: {
    lesson: Lesson;
    meeting: ClassMeeting | null;
    position: string | null;
}) {
    const readings = lesson.readings ?? [];
    const questions = (lesson.questions ?? []).filter(
        (q) => q.kind === 'reflection',
    );
    const materials = lesson.materials ?? [];
    const primary = materials.find((m) => m.is_primary && m.file);
    const complementary = materials.filter(
        (m) => m !== primary && m.type !== 'reference',
    );
    const todayReading = readings.find((r) => r.is_today);
    const lessonUrl = show.url(lesson.slug);

    return (
        <>
            <article className="overflow-hidden rounded-3xl bg-primary text-primary-foreground shadow-sm">
                <div className="p-6 md:p-8">
                    <p className="flex flex-wrap items-center gap-x-2 text-sm font-medium opacity-90">
                        <span>{meeting ? 'Próxima aula' : 'Última aula'}</span>
                        {meeting && (
                            <>
                                <span aria-hidden>·</span>
                                <span className="first-letter:uppercase">
                                    {meeting.date_label}
                                </span>
                            </>
                        )}
                        {position && (
                            <>
                                <span aria-hidden>·</span>
                                <span>{position}</span>
                            </>
                        )}
                    </p>
                    {lesson.number && (
                        <p className="mt-3 text-sm font-semibold tracking-wide uppercase opacity-80">
                            Lição {lesson.number}
                        </p>
                    )}
                    <h2 className="mt-1 font-serif text-3xl leading-tight font-semibold tracking-tight text-balance md:text-4xl">
                        {lesson.title}
                    </h2>
                    {lesson.bible_reference && (
                        <p className="mt-3 flex items-center gap-2 text-lg opacity-95">
                            <BookOpen className="size-5" />
                            Texto base:{' '}
                            <span className="font-semibold">
                                {lesson.bible_reference}
                            </span>
                        </p>
                    )}
                    {lesson.key_verse && (
                        <p className="mt-4 flex gap-2 font-serif leading-relaxed text-pretty italic opacity-95">
                            <KeyRound className="mt-1 size-4 shrink-0" />
                            {lesson.key_verse}
                        </p>
                    )}
                    {lesson.summary && (
                        <p className="mt-4 leading-relaxed text-pretty opacity-90">
                            {lesson.summary}
                        </p>
                    )}
                    <div className="mt-6 flex flex-col gap-2.5 sm:flex-row">
                        <Button
                            asChild
                            size="lg"
                            variant="secondary"
                            className="bg-primary-foreground text-primary hover:bg-primary-foreground/90"
                        >
                            <Link href={lessonUrl}>
                                Abrir a lição <ArrowRight />
                            </Link>
                        </Button>
                        <div className="[&_button]:h-12 [&_button]:w-full [&_button]:border-primary-foreground/30 [&_button]:bg-transparent [&_button]:text-primary-foreground [&_button]:hover:bg-primary-foreground/10 sm:[&_button]:w-auto">
                            <ShareButton
                                url={lesson.url}
                                title={lesson.display_title}
                                text={`📖 ${lesson.display_title}${lesson.bible_reference ? `\nTexto base: ${lesson.bible_reference}` : ''}\n${lesson.url}`}
                            />
                        </div>
                    </div>
                </div>
            </article>

            {todayReading && (
                <Link
                    href={`${lessonUrl}#leituras`}
                    className="mt-4 flex items-center gap-4 rounded-2xl border border-highlight bg-highlight/50 p-4 transition-colors hover:bg-highlight/70"
                >
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-highlight text-highlight-foreground">
                        <Sparkles className="size-5" />
                    </span>
                    <span className="min-w-0">
                        <span className="block text-sm font-medium text-highlight-foreground">
                            Leitura de hoje
                        </span>
                        <span className="block font-serif text-lg font-semibold">
                            {todayReading.reference}
                        </span>
                        {todayReading.notes && (
                            <span className="block text-sm text-muted-foreground">
                                {todayReading.notes}
                            </span>
                        )}
                    </span>
                </Link>
            )}

            <h2 className="mt-10 mb-3 text-lg font-semibold tracking-tight">
                Para estudar durante a semana
            </h2>
            <div className="grid grid-cols-2 gap-3">
                <StudyTile
                    href={primary?.file?.open_url ?? lessonUrl}
                    external={!!primary}
                    icon={<FileText />}
                    title="Lição"
                    detail={
                        primary
                            ? `${primary.file?.extension ?? 'PDF'}${primary.file?.size ? ` · ${primary.file.size}` : ''}`
                            : 'Ler o estudo'
                    }
                />
                <StudyTile
                    href={`${lessonUrl}#leituras`}
                    icon={<CalendarDays />}
                    title="Leituras"
                    detail={
                        readings.length
                            ? `${readings.length} na semana`
                            : 'Texto base'
                    }
                />
                <StudyTile
                    href={`${lessonUrl}#materiais`}
                    icon={<BookMarked />}
                    title="Material complementar"
                    detail={
                        complementary.length
                            ? `${complementary.length} ${complementary.length === 1 ? 'item' : 'itens'}`
                            : 'Nenhum ainda'
                    }
                />
                <StudyTile
                    href={`${lessonUrl}#perguntas`}
                    icon={<HelpCircle />}
                    title="Perguntas"
                    detail={
                        questions.length
                            ? `${questions.length} para refletir`
                            : 'Nenhuma ainda'
                    }
                />
            </div>

            {questions.length > 0 && (
                <section className="mt-10">
                    <h2 className="mb-3 text-lg font-semibold tracking-tight">
                        Para pensar até domingo
                    </h2>
                    <blockquote className="rounded-2xl border-l-4 border-primary bg-card p-5 font-serif text-xl leading-relaxed text-pretty italic shadow-xs">
                        {questions[0].body}
                    </blockquote>
                    {questions.length > 1 && (
                        <Link
                            href={`${lessonUrl}#perguntas`}
                            className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary"
                        >
                            Ver as {questions.length} perguntas{' '}
                            <ArrowRight className="size-4" />
                        </Link>
                    )}
                </section>
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
