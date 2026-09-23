import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarDays,
    ExternalLink,
    EyeOff,
    FileText,
    HelpCircle,
    Layers,
    Paperclip,
    Presentation,
    Send,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import type { BlockKindOption } from '@/components/admin/blocks-manager';
import { BlocksManager } from '@/components/admin/blocks-manager';
import { LessonForm } from '@/components/admin/lesson-form';
import type { MaterialTypeOption } from '@/components/admin/materials-manager';
import { MaterialsManager } from '@/components/admin/materials-manager';
import { QuestionsManager } from '@/components/admin/questions-manager';
import { ReadingsManager } from '@/components/admin/readings-manager';
import { StatusBadge } from '@/components/admin/status-badge';
import { Page, Section } from '@/components/page';
import { Button } from '@/components/ui/button';
import { destroy, status as changeStatus } from '@/routes/admin/lessons';
import { cn } from '@/lib/utils';
import type {
    ClassMeeting,
    Classroom,
    LessonBlock,
    LessonMaterial,
    LessonQuestion,
    LessonReading,
    LessonStatus,
    Option,
    Series,
} from '@/types';

type EditableLesson = {
    id: number;
    classroom: Classroom;
    series_id: number | null;
    number: number | null;
    title: string;
    slug: string;
    url: string;
    sunday_url: string;
    summary: string | null;
    bible_reference: string | null;
    bible_text: string | null;
    magazine_author: string | null;
    key_verse: string | null;
    goal: string | null;
    content: string | null;
    visibility: 'public' | 'members';
    status: LessonStatus;
    status_label: string;
    slug_locked: boolean;
    transitions: LessonStatus[];
    author_ids: number[];
    materials: LessonMaterial[];
    readings: LessonReading[];
    questions: LessonQuestion[];
    blocks: LessonBlock[];
    meetings: ClassMeeting[];
    agenda_url: string;
};

type Props = {
    lesson: EditableLesson;
    series: Series[];
    authors: { id: number; name: string }[];
    visibilities: Option[];
    materialTypes: MaterialTypeOption[];
    weekdays: Option<number>[];
    blockKinds: BlockKindOption[];
};

const transitionButtons: Record<
    LessonStatus,
    {
        label: string;
        icon: ReactNode;
        variant: 'default' | 'outline';
        confirm?: string;
    }
> = {
    published: { label: 'Publicar', icon: <Send />, variant: 'default' },
    draft: {
        label: 'Despublicar',
        icon: <EyeOff />,
        variant: 'outline',
        confirm: 'A lição sairá do ar e voltará a ser rascunho. Continuar?',
    },
};

export default function EditLesson({
    lesson,
    series,
    authors,
    visibilities,
    materialTypes,
    weekdays,
    blockKinds,
}: Props) {
    const transition = (target: LessonStatus) => {
        const config = transitionButtons[target];

        if (config.confirm && !confirm(config.confirm)) {
            return;
        }

        router.post(
            changeStatus.url(lesson.id),
            { status: target },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`Editar: ${lesson.title}`} />

            <Page>
                <header className="mb-6">
                    <p className="text-sm text-muted-foreground">
                        Classe {lesson.classroom.name}
                    </p>
                    <h1 className="mt-1 font-serif text-3xl font-semibold tracking-tight text-balance">
                        {lesson.number ? `Lição ${lesson.number} — ` : ''}
                        {lesson.title}
                    </h1>
                    <div className="mt-2 flex items-center gap-2">
                        <StatusBadge
                            status={lesson.status}
                            label={lesson.status_label}
                        />
                        {lesson.visibility === 'members' && (
                            <span className="text-xs text-muted-foreground">
                                Somente membros
                            </span>
                        )}
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2">
                        {lesson.transitions.map((target) => {
                            const config = transitionButtons[target];

                            return (
                                <Button
                                    key={target}
                                    variant={config.variant}
                                    onClick={() => transition(target)}
                                >
                                    {config.icon} {config.label}
                                </Button>
                            );
                        })}
                        <Button asChild variant="ghost">
                            <a href={lesson.url} target="_blank" rel="noopener">
                                <ExternalLink /> Ver lição
                            </a>
                        </Button>
                        <Button asChild variant="ghost">
                            <Link href={lesson.sunday_url}>
                                <Presentation /> Modo Domingo
                            </Link>
                        </Button>
                    </div>
                    {lesson.status === 'draft' && (
                        <p className="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                            Rascunho: só professores da classe conseguem ver.
                            Publique quando estiver pronta para os alunos.
                        </p>
                    )}
                </header>

                <nav
                    className="-mx-4 mb-8 flex gap-2 overflow-x-auto px-4"
                    aria-label="Seções"
                >
                    {[
                        ['dados', 'Dados'],
                        ['domingos', `Domingos (${lesson.meetings.length})`],
                        ['blocos', `Aprofundamento (${lesson.blocks.length})`],
                        ['leituras', `Leituras (${lesson.readings.length})`],
                        ['materiais', `Materiais (${lesson.materials.length})`],
                        ['perguntas', `Perguntas (${lesson.questions.length})`],
                    ].map(([id, label]) => (
                        <a
                            key={id}
                            href={`#${id}`}
                            className="shrink-0 rounded-full bg-muted px-3.5 py-1.5 text-sm font-medium text-muted-foreground hover:text-foreground"
                        >
                            {label}
                        </a>
                    ))}
                </nav>

                <div className="space-y-14">
                    <Section
                        id="dados"
                        title="Dados da lição"
                        icon={<FileText />}
                    >
                        <LessonForm
                            lessonId={lesson.id}
                            series={series}
                            visibilities={visibilities}
                            authors={authors}
                            slugLocked={lesson.slug_locked}
                            initial={{
                                classroom_id: lesson.classroom.id,
                                series_id: lesson.series_id,
                                number: lesson.number?.toString() ?? '',
                                title: lesson.title,
                                slug: lesson.slug,
                                bible_reference: lesson.bible_reference ?? '',
                                bible_text: lesson.bible_text ?? '',
                                magazine_author: lesson.magazine_author ?? '',
                                key_verse: lesson.key_verse ?? '',
                                goal: lesson.goal ?? '',
                                summary: lesson.summary ?? '',
                                content: lesson.content ?? '',
                                visibility: lesson.visibility,
                                author_ids: lesson.author_ids,
                            }}
                        />
                    </Section>

                    <Section
                        id="domingos"
                        title="Domingos desta lição"
                        icon={<CalendarCheck />}
                        description="Uma lição pode ocupar mais de um domingo. As datas são definidas na agenda da classe."
                    >
                        {lesson.meetings.length > 0 ? (
                            <ul className="divide-y rounded-2xl border bg-card">
                                {lesson.meetings.map((meeting, index) => (
                                    <li
                                        key={meeting.id}
                                        className="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                                    >
                                        <span className="first-letter:uppercase">
                                            {meeting.date_label}
                                            {lesson.meetings.length > 1 &&
                                                ` · encontro ${index + 1} de ${lesson.meetings.length}`}
                                        </span>
                                        <span
                                            className={cn(
                                                'rounded-full px-2.5 py-0.5 text-xs font-medium',
                                                meeting.status === 'held'
                                                    ? 'bg-muted text-muted-foreground'
                                                    : 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
                                            )}
                                        >
                                            {meeting.status_label}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="rounded-2xl border border-dashed p-4 text-sm text-muted-foreground">
                                Esta lição ainda não está em nenhum domingo.
                            </p>
                        )}
                        <Button asChild variant="outline" className="mt-3">
                            <Link href={lesson.agenda_url}>
                                <CalendarDays /> Abrir agenda da classe
                            </Link>
                        </Button>
                    </Section>

                    <Section
                        id="blocos"
                        title="Aprofundamento"
                        icon={<Layers />}
                        description="Roteiro, contexto histórico, teologia, curiosidades, conceitos e notas de precisão. Marque o que é só do professor."
                    >
                        <BlocksManager
                            lessonId={lesson.id}
                            blocks={lesson.blocks}
                            kinds={blockKinds}
                            weekdays={weekdays}
                        />
                    </Section>

                    <Section
                        id="leituras"
                        title="Leituras da semana"
                        icon={<CalendarDays />}
                        description="Uma leitura por dia ajuda a turma a chegar preparada."
                    >
                        <ReadingsManager
                            lessonId={lesson.id}
                            readings={lesson.readings}
                            weekdays={weekdays}
                        />
                    </Section>

                    <Section
                        id="materiais"
                        title="Materiais"
                        icon={<Paperclip />}
                        description="PDFs, arquivos, vídeos, áudios, links e referências."
                    >
                        <MaterialsManager
                            lessonId={lesson.id}
                            materials={lesson.materials}
                            types={materialTypes}
                        />
                    </Section>

                    <Section
                        id="perguntas"
                        title="Perguntas"
                        icon={<HelpCircle />}
                        description="Reflexão para discutir no domingo; revisão (com gabarito) para o aluno conferir o que aprendeu."
                    >
                        <QuestionsManager
                            lessonId={lesson.id}
                            questions={lesson.questions}
                        />
                    </Section>

                    <div className="border-t pt-6">
                        <Button
                            variant="ghost"
                            className="text-destructive hover:text-destructive"
                            onClick={() => {
                                if (
                                    confirm(
                                        'Excluir esta lição? Ela sairá do site e da biblioteca.',
                                    )
                                ) {
                                    router.delete(destroy.url(lesson.id));
                                }
                            }}
                        >
                            <Trash2 /> Excluir lição
                        </Button>
                    </div>
                </div>
            </Page>
        </>
    );
}
