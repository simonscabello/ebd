import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpenText,
    BookText,
    CalendarDays,
    ChevronDown,
    ChevronUp,
    KeyRound,
    Layers,
    Library,
    Lightbulb,
    Lock,
    NotebookPen,
    NotebookText,
    Paperclip,
    PenLine,
    Presentation,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { BiblePassage } from '@/components/lesson/bible-passage';
import { countdownLabel } from '@/components/lesson/countdown';
import { LessonAudioPlayer } from '@/components/lesson/lesson-audio';
import {
    BlockAccordion,
    BlockCards,
    groupStudentBlocks,
} from '@/components/lesson/lesson-blocks';
import { MaterialCard, MaterialIcon } from '@/components/lesson/material-card';
import { ReadingPlan } from '@/components/lesson/reading-plan';
import { PersonalNote } from '@/components/lesson/personal-note';
import { RevistaHeader } from '@/components/lesson/revista-header';
import { RichText } from '@/components/lesson/rich-text';
import { ShareButton } from '@/components/lesson/share-button';
import { Page, Section } from '@/components/page';
import { SectionNav } from '@/components/section-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useReadingCheckin } from '@/hooks/use-reading-checkin';
import { home, library, login } from '@/routes';
import { edit } from '@/routes/admin/lessons';
import { sunday } from '@/routes/lessons';
import type { Lesson, LessonAudioFile } from '@/types';

type Study = {
    checked_weekdays: number[];
    note: string | null;
    today: string;
};

type Props = {
    lesson: Lesson;
    canManage: boolean;
    shareText: string;
    /** Estudo da própria pessoa; só para membros da classe. */
    study: Study | null;
    /** "Ouvir estudo"; null enquanto não há áudio gerado. */
    audio: LessonAudioFile | null;
};

export default function LessonShow({
    lesson,
    canManage,
    shareText,
    study,
    audio,
}: Props) {
    const { auth } = usePage().props;
    const checkin = useReadingCheckin(lesson.slug);

    const materials = lesson.materials ?? [];
    const readings = lesson.readings ?? [];
    // As leituras também estão no Início e em Leituras da semana: aqui ficam recolhidas na de
    // hoje (ou na próxima por ler), e abrem inteiras pelo botão ou por #leituras.
    const collapsible = readings.length > 2;
    const featuredReading =
        readings.find((r) => r.is_today) ??
        readings.find(
            (r) =>
                r.weekday !== null &&
                !(study?.checked_weekdays ?? []).includes(r.weekday),
        ) ??
        null;
    const readingsDone = readings.filter(
        (r) =>
            r.weekday !== null &&
            (study?.checked_weekdays ?? []).includes(r.weekday),
    ).length;
    const [readingsExpanded, setReadingsExpanded] = useState(false);

    useEffect(() => {
        const expandOnHash = () => {
            if (window.location.hash === '#leituras') {
                setReadingsExpanded(true);
            }
        };

        expandOnHash();
        window.addEventListener('hashchange', expandOnHash);

        return () => window.removeEventListener('hashchange', expandOnHash);
    }, []);

    // Link "Ouvir" compartilhado no WhatsApp (#ouvir): leva direto ao player e
    // destaca o play. Tocar sozinho o navegador não deixa.
    const [listenHighlight, setListenHighlight] = useState(false);

    useEffect(() => {
        if (window.location.hash !== '#ouvir' || !audio) {
            return;
        }

        let hideTimer: ReturnType<typeof setTimeout> | undefined;

        // Espera a tela de abertura sair, senão o destaque passa despercebido.
        const waitSplash = setInterval(() => {
            if (document.getElementById('splash')) {
                return;
            }

            clearInterval(waitSplash);
            document
                .getElementById('ouvir')
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setListenHighlight(true);
            hideTimer = setTimeout(() => setListenHighlight(false), 5000);
        }, 150);

        return () => {
            clearInterval(waitSplash);
            clearTimeout(hideTimer);
        };
    }, [audio]);
    const { deepen, curiosities, concepts, other } = groupStudentBlocks(
        lesson.blocks ?? [],
    );
    const teacherBlocks = lesson.teacher_blocks ?? [];

    const primary = materials.filter(
        (m) => m.is_primary && m.type !== 'reference',
    );
    const complementary = materials.filter(
        (m) => !m.is_primary && m.type !== 'reference',
    );
    const references = materials.filter((m) => m.type === 'reference');

    const sections = [
        readings.length > 0 && { id: 'leituras', label: 'Leituras' },
        lesson.content_html && { id: 'estudo', label: 'Estudo' },
        (deepen.length > 0 || other.length > 0) && {
            id: 'aprofunde',
            label: 'Aprofunde',
        },
        curiosities.length > 0 && { id: 'curiosidades', label: 'Curiosidades' },
        concepts.length > 0 && { id: 'conceitos', label: 'Conceitos' },
        (primary.length > 0 || complementary.length > 0) && {
            id: 'materiais',
            label: 'Materiais',
        },
        references.length > 0 && { id: 'referencias', label: 'Referências' },
        study && { id: 'anotacoes', label: 'Anotações' },
        teacherBlocks.length > 0 && { id: 'professor', label: 'Professor' },
    ].filter(Boolean) as { id: string; label: string }[];

    // A data vem dos encontros: o próximo domingo da lição ou o último, se já passou.
    const meetings = lesson.meetings ?? [];
    const nextMeeting = meetings.find((m) => m.days_until >= 0);
    const shownMeeting = nextMeeting ?? meetings[meetings.length - 1];
    const meetingPosition =
        shownMeeting && meetings.length > 1
            ? `encontro ${meetings.indexOf(shownMeeting) + 1} de ${meetings.length}`
            : null;
    const countdown = nextMeeting
        ? countdownLabel(nextMeeting.days_until)
        : shownMeeting
          ? 'Aula realizada'
          : null;

    return (
        <>
            <Head title={lesson.display_title}>
                <meta
                    name="description"
                    content={lesson.summary ?? `Lição: ${lesson.title}`}
                />
                <meta property="og:title" content={lesson.title} />
                <meta
                    property="og:description"
                    content={lesson.summary ?? lesson.bible_reference ?? ''}
                />
                <meta property="og:type" content="article" />
                <meta property="og:url" content={lesson.url} />
            </Head>

            <Page>
                <Link
                    href={home()}
                    className="mb-5 -ml-1 inline-flex min-h-9 items-center gap-1 rounded-lg px-1 text-sm text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <ArrowLeft className="size-4" /> Início
                </Link>

                <header>
                    <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                        {lesson.classroom && (
                            <span>Classe {lesson.classroom.name}</span>
                        )}
                        {lesson.series && (
                            <>
                                <span aria-hidden>·</span>
                                <Link
                                    href={library({
                                        query: { serie: lesson.series.id },
                                    })}
                                    className="font-medium text-primary hover:underline"
                                >
                                    {lesson.series.title}
                                </Link>
                            </>
                        )}
                    </p>
                    {lesson.number && (
                        <p className="mt-3 text-sm font-semibold tracking-wide text-primary uppercase">
                            Lição {lesson.number}
                        </p>
                    )}
                    <h1 className="mt-1 font-serif text-4xl leading-tight font-semibold tracking-tight text-balance md:text-5xl">
                        {lesson.title}
                    </h1>
                    <div className="mt-3 flex flex-wrap items-center gap-2 text-muted-foreground">
                        {shownMeeting && (
                            <span className="inline-flex items-center gap-1.5 first-letter:uppercase">
                                <CalendarDays className="size-4" />{' '}
                                {shownMeeting.date_label}
                                {meetingPosition && ` · ${meetingPosition}`}
                            </span>
                        )}
                        {countdown && lesson.status !== 'draft' && (
                            <Badge variant="secondary" className="rounded-full">
                                {countdown}
                            </Badge>
                        )}
                        {lesson.status === 'draft' && (
                            <Badge className="rounded-full bg-amber-500 text-white">
                                Rascunho
                            </Badge>
                        )}
                        {lesson.visibility === 'members' && (
                            <Badge variant="outline" className="rounded-full">
                                <Lock /> Só membros
                            </Badge>
                        )}
                    </div>
                </header>

                {lesson.bible_reference && (
                    <div className="mt-6">
                        <BiblePassage
                            reference={lesson.bible_reference}
                            passage={lesson.bible_passage}
                        />
                    </div>
                )}

                {(lesson.key_verse || lesson.goal) && (
                    <div className="mt-3">
                        <RevistaHeader lesson={lesson} />
                    </div>
                )}

                <div className="mt-5 flex flex-wrap gap-2 *:flex-1 sm:*:flex-none">
                    <ShareButton
                        url={lesson.url}
                        title={lesson.title}
                        text={shareText}
                    />
                    <Button asChild variant="outline">
                        <Link href={sunday(lesson.slug)}>
                            <Presentation /> Modo Domingo
                        </Link>
                    </Button>
                    {canManage && (
                        <Button asChild variant="outline">
                            <Link href={edit(lesson.id)}>
                                <PenLine /> Editar
                            </Link>
                        </Button>
                    )}
                </div>

                {lesson.summary && (
                    <p className="mt-8 font-serif text-xl leading-relaxed text-pretty text-foreground/90">
                        {lesson.summary}
                    </p>
                )}

                {sections.length > 1 && (
                    <SectionNav
                        sections={sections}
                        label="Seções da lição"
                        progress
                        className="mt-8"
                    />
                )}

                <div className="mt-8 space-y-12">
                    {readings.length > 0 && (
                        <Section
                            id="leituras"
                            title="Preparação da semana"
                            icon={<CalendarDays />}
                            description="Um pouco por dia até domingo."
                        >
                            {!readingsExpanded && collapsible && (
                                <p className="mb-3 text-sm text-muted-foreground">
                                    {study
                                        ? `${readingsDone} de ${readings.length} leituras feitas.`
                                        : `${readings.length} leituras, uma por dia.`}{' '}
                                    {featuredReading
                                        ? featuredReading.is_today
                                            ? 'A de hoje:'
                                            : 'A próxima:'
                                        : ''}
                                </p>
                            )}
                            <ReadingPlan
                                readings={
                                    readingsExpanded || !collapsible
                                        ? readings
                                        : [featuredReading ?? readings[0]]
                                }
                                tracking={
                                    study
                                        ? {
                                              checkedWeekdays:
                                                  study.checked_weekdays,
                                              pendingWeekdays: readings
                                                  .map((r) => r.weekday)
                                                  .filter(
                                                      (day): day is number =>
                                                          day !== null &&
                                                          checkin.isPending(
                                                              day,
                                                          ),
                                                  ),
                                              onToggle: (reading, done) =>
                                                  reading.weekday !== null &&
                                                  checkin.toggle(
                                                      reading.weekday,
                                                      done,
                                                      reading.id,
                                                  ),
                                          }
                                        : undefined
                                }
                            />
                            {collapsible && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-3"
                                    aria-expanded={readingsExpanded}
                                    onClick={() =>
                                        setReadingsExpanded((open) => !open)
                                    }
                                >
                                    {readingsExpanded ? (
                                        <>
                                            <ChevronUp /> Mostrar menos
                                        </>
                                    ) : (
                                        <>
                                            <ChevronDown /> Ver as{' '}
                                            {readings.length} leituras
                                        </>
                                    )}
                                </Button>
                            )}
                            {!study && !auth.user && (
                                <p className="mt-4 flex items-center gap-3 rounded-2xl bg-muted/70 px-4 py-3 text-sm text-muted-foreground">
                                    <KeyRound className="size-4 shrink-0 text-primary" />
                                    <span>
                                        Membro da classe? Entre com o seu link
                                        de acesso para marcar as leituras e
                                        fazer anotações.{' '}
                                        <Link
                                            href={login()}
                                            className="font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            Entrar
                                        </Link>
                                    </span>
                                </p>
                            )}
                        </Section>
                    )}

                    {lesson.content_html && (
                        <Section id="estudo" title="Estudo" icon={<BookText />}>
                            {audio && (
                                <div id="ouvir" className="mb-6 scroll-mt-24">
                                    <LessonAudioPlayer
                                        src={audio.url}
                                        knownDuration={audio.duration}
                                        highlight={listenHighlight}
                                    />
                                    <div className="mt-2 flex justify-end">
                                        <ShareButton
                                            title={lesson.display_title}
                                            text={audio.share_text}
                                            variant="outline"
                                            size="sm"
                                            label="Compartilhar áudio"
                                        />
                                    </div>
                                </div>
                            )}
                            <RichText
                                className="reading"
                                html={lesson.content_html}
                            />
                        </Section>
                    )}

                    {(deepen.length > 0 || other.length > 0) && (
                        <Section
                            id="aprofunde"
                            title="Aprofunde"
                            icon={<Layers />}
                            description="Contexto histórico, teologia e aplicação para ir além da revista."
                        >
                            <BlockCards blocks={[...deepen, ...other]} />
                        </Section>
                    )}

                    {curiosities.length > 0 && (
                        <Section
                            id="curiosidades"
                            title="Curiosidades"
                            icon={<Lightbulb />}
                            description="Detalhes que fazem o texto ganhar vida."
                        >
                            <BlockCards blocks={curiosities} tone="highlight" />
                        </Section>
                    )}

                    {concepts.length > 0 && (
                        <Section
                            id="conceitos"
                            title="Conceitos citados"
                            icon={<BookOpenText />}
                            description="Toque em um conceito para ler a explicação."
                        >
                            <BlockAccordion blocks={concepts} />
                        </Section>
                    )}

                    {(primary.length > 0 || complementary.length > 0) && (
                        <Section
                            id="materiais"
                            title="Materiais"
                            icon={<Paperclip />}
                        >
                            <div className="space-y-3">
                                {primary.map((material) => (
                                    <MaterialCard
                                        key={material.id}
                                        material={material}
                                    />
                                ))}
                            </div>
                            {complementary.length > 0 && (
                                <>
                                    {primary.length > 0 && (
                                        <h3 className="mt-6 mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                            Material complementar
                                        </h3>
                                    )}
                                    <div className="space-y-3">
                                        {complementary.map((material) => (
                                            <MaterialCard
                                                key={material.id}
                                                material={material}
                                            />
                                        ))}
                                    </div>
                                </>
                            )}
                        </Section>
                    )}

                    {references.length > 0 && (
                        <Section
                            id="referencias"
                            title="Referências"
                            icon={<Library />}
                        >
                            <ul className="space-y-3">
                                {references.map((reference) => (
                                    <li
                                        key={reference.id}
                                        className="flex gap-3"
                                    >
                                        <MaterialIcon
                                            type="reference"
                                            className="mt-1 size-5 shrink-0 text-muted-foreground"
                                        />
                                        <div>
                                            {reference.url ? (
                                                <a
                                                    href={reference.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer nofollow"
                                                    className="font-medium text-primary hover:underline"
                                                >
                                                    {reference.title}
                                                </a>
                                            ) : (
                                                <p className="font-medium">
                                                    {reference.title}
                                                </p>
                                            )}
                                            {reference.description && (
                                                <p className="text-sm text-muted-foreground">
                                                    {reference.description}
                                                </p>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </Section>
                    )}

                    {study && (
                        <Section
                            id="anotacoes"
                            title="Minhas anotações"
                            icon={<NotebookText />}
                        >
                            <PersonalNote
                                lessonSlug={lesson.slug}
                                initial={study.note}
                            />
                        </Section>
                    )}

                    {teacherBlocks.length > 0 && (
                        <Section
                            id="professor"
                            title="Para o professor"
                            icon={<NotebookPen />}
                            description="Visível apenas para quem gerencia a classe."
                        >
                            <BlockCards blocks={teacherBlocks} tone="teacher" />
                        </Section>
                    )}

                    {lesson.authors && lesson.authors.length > 0 && (
                        <p className="border-t pt-6 text-sm text-muted-foreground">
                            Preparado por {lesson.authors.join(', ')}.
                        </p>
                    )}
                </div>
            </Page>
        </>
    );
}
