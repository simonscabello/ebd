import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpenText,
    CalendarDays,
    KeyRound,
    Layers,
    Library,
    Lightbulb,
    Lock,
    MessageCircleQuestion,
    PenLine,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
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
import {
    StudyHelperPrompt,
    StudyHelperSheet,
} from '@/components/lesson/study-helper';
import type { StudyHelperInfo } from '@/components/lesson/study-helper';
import { Page, Section } from '@/components/page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useReadingCheckin } from '@/hooks/use-reading-checkin';
import { cn } from '@/lib/utils';
import { home, library, login } from '@/routes';
import { edit } from '@/routes/admin/lessons';
import { show } from '@/routes/lessons';
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
    /** "Tirar dúvida" com a IA; só para membros da classe. */
    helper: StudyHelperInfo | null;
};

type TabId = 'estudo' | 'leituras' | 'materiais' | 'anotacoes' | 'professor';

/** Links antigos e do Início (#materiais, #anotacoes…) abrem na aba certa. */
const tabByHash: Record<string, TabId> = {
    '#estudo': 'estudo',
    '#ouvir': 'estudo',
    '#leituras': 'leituras',
    '#materiais': 'materiais',
    '#referencias': 'materiais',
    '#anotacoes': 'anotacoes',
    '#professor': 'professor',
};

/** Altura da barra do topo do app (top-16): a faixa das abas gruda logo abaixo. */
const TOP_BAR = 64;

export default function LessonShow({
    lesson,
    canManage,
    shareText,
    study,
    audio,
    helper,
}: Props) {
    const { auth } = usePage().props;
    const [helperOpen, setHelperOpen] = useState(false);
    const checkin = useReadingCheckin(lesson.slug);

    const materials = lesson.materials ?? [];
    const readings = lesson.readings ?? [];
    const topics = lesson.topics ?? [];
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

    const tabs = [
        { id: 'estudo', label: 'Estudo' },
        readings.length > 0 && { id: 'leituras', label: 'Leituras' },
        materials.length > 0 && {
            id: 'materiais',
            label: 'Materiais',
            count: materials.length,
        },
        study && { id: 'anotacoes', label: 'Anotações' },
        teacherBlocks.length > 0 && { id: 'professor', label: 'Professor' },
    ].filter(Boolean) as { id: TabId; label: string; count?: number }[];

    const has = (id: TabId) => tabs.some((t) => t.id === id);
    const tabIds = tabs.map((t) => t.id).join(',');
    const [tab, setTab] = useState<TabId>('estudo');
    const tabsTop = useRef<HTMLDivElement>(null);
    const studyPanel = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const fromHash = () => {
            const target = tabByHash[window.location.hash];

            if (target && tabIds.split(',').includes(target)) {
                setTab(target);
            }
        };

        fromHash();
        window.addEventListener('hashchange', fromHash);

        return () => window.removeEventListener('hashchange', fromHash);
    }, [tabIds]);

    const choose = (id: TabId) => {
        setTab(id);
        window.history.replaceState(window.history.state, '', `#${id}`);

        // Trocar de aba com a faixa já grudada no topo: volta ao começo da aba.
        const top = tabsTop.current?.getBoundingClientRect().top ?? 0;

        if (top < TOP_BAR) {
            window.scrollTo({ top: window.scrollY + top - TOP_BAR });
        }
    };

    const goToTopic = (index: number) => {
        studyPanel.current
            ?.querySelectorAll<HTMLElement>('.reading h2, .reading h3')
            [index]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

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

    // A data vem dos encontros: o próximo domingo da lição ou o último, se já passou.
    const meetings = lesson.meetings ?? [];
    const nextMeeting = meetings.find((m) => m.days_until >= 0);
    const shownMeeting = nextMeeting ?? meetings[meetings.length - 1];
    const meetingPosition =
        shownMeeting && meetings.indexOf(shownMeeting) > 0
            ? `${meetings.indexOf(shownMeeting) + 1}º encontro`
            : null;
    const countdown = nextMeeting
        ? countdownLabel(
              nextMeeting.days_until,
              lesson.classroom?.program_short_name ?? 'EBD',
          )
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
                <div className="mb-5 flex items-center justify-between gap-2">
                    <Link
                        href={home()}
                        className="-ml-1 inline-flex min-h-9 items-center gap-1 rounded-lg px-1 text-sm text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <ArrowLeft className="size-4" /> Início
                    </Link>
                    <div className="flex gap-2">
                        <ShareButton
                            url={lesson.url}
                            title={lesson.title}
                            text={shareText}
                            size="sm"
                        />
                        {canManage && (
                            <Button asChild variant="outline" size="sm">
                                <Link href={edit(lesson.id)}>
                                    <PenLine /> Editar
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

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

                {lesson.summary && (
                    <p className="mt-8 font-serif text-xl leading-relaxed text-pretty text-foreground/90">
                        {lesson.summary}
                    </p>
                )}

                {/* Âncoras dos links (#materiais, #anotacoes…): param na faixa das abas. */}
                <div ref={tabsTop} className="mt-8">
                    {tabs.map((item) => (
                        <span
                            key={item.id}
                            id={item.id}
                            className="block scroll-mt-16"
                        />
                    ))}
                </div>
                <div className="sticky top-16 z-20 -mx-4 flex items-center gap-2 border-b bg-background/90 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/80 sm:-mx-6 sm:px-6">
                    <div
                        role="tablist"
                        aria-label="Partes da lição"
                        className="flex min-w-0 flex-1 [scrollbar-width:none] gap-4 overflow-x-auto"
                    >
                        {tabs.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                role="tab"
                                id={`aba-${item.id}`}
                                aria-selected={tab === item.id}
                                aria-controls={`painel-${item.id}`}
                                onClick={() => choose(item.id)}
                                className={cn(
                                    '-mb-px inline-flex h-12 shrink-0 items-center gap-1.5 border-b-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    tab === item.id
                                        ? 'border-primary text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {item.label}
                                {item.count !== undefined && (
                                    <span className="rounded-full bg-muted px-1.5 text-[11px] font-semibold text-muted-foreground">
                                        {item.count}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>
                    {helper && (
                        <button
                            type="button"
                            onClick={() => setHelperOpen(true)}
                            aria-label="Tirar dúvida sobre esta lição"
                            className="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent text-accent-foreground transition-colors hover:bg-accent/80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <MessageCircleQuestion className="size-5" />
                        </button>
                    )}
                </div>

                <TabPanel id="estudo" active={tab}>
                    <div ref={studyPanel} className="space-y-12">
                        {(audio ||
                            topics.length > 1 ||
                            lesson.content_html) && (
                            <div>
                                {audio && (
                                    <div
                                        id="ouvir"
                                        className="mb-6 scroll-mt-32"
                                    >
                                        <LessonAudioPlayer
                                            src={audio.url}
                                            title={lesson.display_title}
                                            href={show.url(lesson.slug)}
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
                                {topics.length > 1 && (
                                    <nav
                                        aria-label="Partes do estudo"
                                        className="-mx-4 mb-6 flex [scrollbar-width:none] gap-2 overflow-x-auto px-4"
                                    >
                                        {topics.map((topic, index) => (
                                            <button
                                                key={`${topic}-${index}`}
                                                type="button"
                                                onClick={() => goToTopic(index)}
                                                className="inline-flex min-h-9 shrink-0 items-center rounded-full border px-3 text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                {index + 1} · {topic}
                                            </button>
                                        ))}
                                    </nav>
                                )}
                                {lesson.content_html && (
                                    <RichText
                                        className="reading [&_h2]:scroll-mt-32 [&_h3]:scroll-mt-32"
                                        html={lesson.content_html}
                                    />
                                )}
                                {helper && (
                                    <StudyHelperPrompt
                                        onOpen={() => setHelperOpen(true)}
                                    />
                                )}
                            </div>
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
                                <BlockCards
                                    blocks={curiosities}
                                    tone="highlight"
                                />
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
                    </div>
                </TabPanel>

                {has('leituras') && (
                    <TabPanel id="leituras" active={tab}>
                        <p className="mb-4 text-sm text-muted-foreground">
                            Um pouco por dia até{' '}
                            {lesson.classroom?.meeting_day ?? 'domingo'}.
                        </p>
                        <ReadingPlan
                            readings={readings}
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
                                                      checkin.isPending(day),
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
                        {!auth.user && (
                            <p className="mt-4 flex items-center gap-3 rounded-2xl bg-muted/70 px-4 py-3 text-sm text-muted-foreground">
                                <KeyRound className="size-4 shrink-0 text-primary" />
                                <span>
                                    Membro da classe? Entre com o seu link de
                                    acesso para marcar as leituras e fazer
                                    anotações.{' '}
                                    <Link
                                        href={login()}
                                        className="font-medium text-primary underline-offset-4 hover:underline"
                                    >
                                        Entrar
                                    </Link>
                                </span>
                            </p>
                        )}
                    </TabPanel>
                )}

                {has('materiais') && (
                    <TabPanel id="materiais" active={tab}>
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
                        {references.length > 0 && (
                            <>
                                <h3 className="mt-8 mb-3 flex items-center gap-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                    <Library className="size-4" /> Referências
                                </h3>
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
                            </>
                        )}
                    </TabPanel>
                )}

                {study && (
                    <TabPanel id="anotacoes" active={tab}>
                        <PersonalNote
                            lessonSlug={lesson.slug}
                            initial={study.note}
                            classroom={lesson.classroom}
                        />
                    </TabPanel>
                )}

                {has('professor') && (
                    <TabPanel id="professor" active={tab}>
                        <p className="mb-4 text-sm text-muted-foreground">
                            Visível apenas para quem gerencia a classe.
                        </p>
                        <BlockCards blocks={teacherBlocks} tone="teacher" />
                    </TabPanel>
                )}

                {lesson.authors && lesson.authors.length > 0 && (
                    <p className="mt-12 border-t pt-6 text-sm text-muted-foreground">
                        Preparado por {lesson.authors.join(', ')}.
                    </p>
                )}
            </Page>

            {helper && (
                <StudyHelperSheet
                    lesson={lesson}
                    helper={helper}
                    open={helperOpen}
                    onOpenChange={setHelperOpen}
                />
            )}
        </>
    );
}

/** Painel de uma aba: fica montado (anotação, player) e só se esconde. */
function TabPanel({
    id,
    active,
    children,
}: {
    id: TabId;
    active: TabId;
    children: ReactNode;
}) {
    return (
        <div
            role="tabpanel"
            id={`painel-${id}`}
            aria-labelledby={`aba-${id}`}
            hidden={active !== id}
            className="pt-6"
        >
            {children}
        </div>
    );
}
