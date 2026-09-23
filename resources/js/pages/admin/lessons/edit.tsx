import { Head, Link, router } from '@inertiajs/react';
import {
    Archive,
    CalendarDays,
    ExternalLink,
    EyeOff,
    FileText,
    HelpCircle,
    Paperclip,
    Presentation,
    RotateCcw,
    Send,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { LessonForm } from '@/components/admin/lesson-form';
import type { MaterialTypeOption } from '@/components/admin/materials-manager';
import { MaterialsManager } from '@/components/admin/materials-manager';
import { QuestionsManager } from '@/components/admin/questions-manager';
import { ReadingsManager } from '@/components/admin/readings-manager';
import { StatusBadge } from '@/components/admin/status-badge';
import { Page, Section } from '@/components/page';
import { Button } from '@/components/ui/button';
import { destroy, status as changeStatus } from '@/routes/admin/lessons';
import type {
    Classroom,
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
    title: string;
    slug: string;
    url: string;
    sunday_url: string;
    summary: string | null;
    scheduled_for: string | null;
    bible_reference: string | null;
    bible_text: string | null;
    content: string | null;
    teacher_notes: string | null;
    visibility: 'public' | 'members';
    status: LessonStatus;
    status_label: string;
    slug_locked: boolean;
    transitions: LessonStatus[];
    author_ids: number[];
    materials: LessonMaterial[];
    readings: LessonReading[];
    questions: LessonQuestion[];
};

type Props = {
    lesson: EditableLesson;
    series: Series[];
    authors: { id: number; name: string }[];
    visibilities: Option[];
    materialTypes: MaterialTypeOption[];
    weekdays: Option<number>[];
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
    completed: {
        label: 'Concluir aula',
        icon: <Archive />,
        variant: 'outline',
    },
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
                            const config =
                                lesson.status === 'completed' &&
                                target === 'published'
                                    ? {
                                          label: 'Reabrir',
                                          icon: <RotateCcw />,
                                          variant: 'outline' as const,
                                      }
                                    : transitionButtons[target];

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
                                title: lesson.title,
                                slug: lesson.slug,
                                scheduled_for: lesson.scheduled_for ?? '',
                                bible_reference: lesson.bible_reference ?? '',
                                bible_text: lesson.bible_text ?? '',
                                summary: lesson.summary ?? '',
                                content: lesson.content ?? '',
                                teacher_notes: lesson.teacher_notes ?? '',
                                visibility: lesson.visibility,
                                author_ids: lesson.author_ids,
                            }}
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
                        title="Perguntas para reflexão"
                        icon={<HelpCircle />}
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
