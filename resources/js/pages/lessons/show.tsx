import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpenText,
    BookText,
    CalendarDays,
    CheckSquare,
    HelpCircle,
    Layers,
    Library,
    Lightbulb,
    Lock,
    NotebookPen,
    Paperclip,
    PenLine,
    Presentation,
} from 'lucide-react';
import { BiblePassage } from '@/components/lesson/bible-passage';
import { countdownLabel } from '@/components/lesson/countdown';
import {
    BlockAccordion,
    BlockCards,
    groupStudentBlocks,
} from '@/components/lesson/lesson-blocks';
import { MaterialCard, MaterialIcon } from '@/components/lesson/material-card';
import { QuestionList } from '@/components/lesson/question-list';
import { ReadingPlan } from '@/components/lesson/reading-plan';
import { ReviewQuiz } from '@/components/lesson/review-quiz';
import { RevistaHeader } from '@/components/lesson/revista-header';
import { ShareButton } from '@/components/lesson/share-button';
import { Page, Section } from '@/components/page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { home, library } from '@/routes';
import { edit } from '@/routes/admin/lessons';
import { sunday } from '@/routes/lessons';
import type { Lesson } from '@/types';

type Props = {
    lesson: Lesson;
    canManage: boolean;
    shareText: string;
};

export default function LessonShow({ lesson, canManage, shareText }: Props) {
    const materials = lesson.materials ?? [];
    const readings = lesson.readings ?? [];
    const questions = lesson.questions ?? [];
    const reflection = questions.filter((q) => q.kind === 'reflection');
    const review = questions.filter((q) => q.kind === 'review');
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
        reflection.length > 0 && { id: 'perguntas', label: 'Perguntas' },
        review.length > 0 && { id: 'revisao', label: 'Revisão' },
        references.length > 0 && { id: 'referencias', label: 'Referências' },
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
                    className="mb-5 -ml-1 inline-flex items-center gap-1 rounded-lg px-1 py-1 text-sm text-muted-foreground hover:text-foreground"
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
                            text={lesson.bible_text}
                        />
                    </div>
                )}

                {(lesson.key_verse || lesson.goal) && (
                    <div className="mt-3">
                        <RevistaHeader lesson={lesson} />
                    </div>
                )}

                <div className="mt-5 flex flex-wrap gap-2">
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
                        <Button asChild variant="ghost">
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
                    <nav
                        className="sticky top-16 z-20 -mx-4 mt-8 flex gap-2 overflow-x-auto border-b border-border/60 bg-background/90 px-4 py-2.5 backdrop-blur"
                        aria-label="Seções da lição"
                    >
                        {sections.map((section) => (
                            <a
                                key={section.id}
                                href={`#${section.id}`}
                                className="shrink-0 rounded-full bg-muted px-3.5 py-1.5 text-sm font-medium text-muted-foreground hover:text-foreground"
                            >
                                {section.label}
                            </a>
                        ))}
                    </nav>
                )}

                <div className="mt-8 space-y-12">
                    {readings.length > 0 && (
                        <Section
                            id="leituras"
                            title="Preparação da semana"
                            icon={<CalendarDays />}
                            description="Um pouco por dia até domingo."
                        >
                            <ReadingPlan readings={readings} />
                        </Section>
                    )}

                    {lesson.content_html && (
                        <Section id="estudo" title="Estudo" icon={<BookText />}>
                            <div
                                className="reading"
                                // HTML gerado no servidor a partir de Markdown, com HTML bruto removido.
                                dangerouslySetInnerHTML={{
                                    __html: lesson.content_html,
                                }}
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

                    {reflection.length > 0 && (
                        <Section
                            id="perguntas"
                            title="Perguntas para reflexão"
                            icon={<HelpCircle />}
                            description="Pense nelas durante a semana. Vamos conversar sobre elas no domingo."
                        >
                            <QuestionList questions={reflection} />
                        </Section>
                    )}

                    {review.length > 0 && (
                        <Section
                            id="revisao"
                            title="Revise o que aprendeu"
                            icon={<CheckSquare />}
                            description="Responda, confira o gabarito e veja como você foi."
                        >
                            <ReviewQuiz questions={review} />
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
