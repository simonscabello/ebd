import { Head, Link, router } from '@inertiajs/react';
import {
    AudioLines,
    CalendarCheck,
    CalendarDays,
    ExternalLink,
    EyeOff,
    FileText,
    Layers,
    Paperclip,
    Presentation,
    Send,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import type { BlockKindOption } from '@/components/admin/blocks-manager';
import { BlocksManager } from '@/components/admin/blocks-manager';
import { LessonAudioManager } from '@/components/admin/lesson-audio-manager';
import { LessonForm } from '@/components/admin/lesson-form';
import type { MaterialTypeOption } from '@/components/admin/materials-manager';
import { MaterialsManager } from '@/components/admin/materials-manager';
import { ReadingsManager } from '@/components/admin/readings-manager';
import { MeetingStatusBadge } from '@/components/admin/meeting-status-badge';
import { StatusBadge } from '@/components/admin/status-badge';
import { useConfirm } from '@/components/confirm-dialog';
import { Breadcrumbs, Page, Section } from '@/components/page';
import { SectionNav } from '@/components/section-nav';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes/admin';
import {
    destroy,
    index as lessonsIndex,
    status as changeStatus,
} from '@/routes/admin/lessons';
import type {
    ClassMeeting,
    Classroom,
    LessonAudioManage,
    LessonBlock,
    LessonMaterial,
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
    blocks: LessonBlock[];
    meetings: (ClassMeeting & { url: string })[];
    meetings_url: string;
};

type Props = {
    lesson: EditableLesson;
    series: Series[];
    authors: { id: number; name: string }[];
    visibilities: Option[];
    materialTypes: MaterialTypeOption[];
    weekdays: Option<number>[];
    blockKinds: BlockKindOption[];
    audio: LessonAudioManage;
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
    audio,
}: Props) {
    const confirm = useConfirm();

    const transition = async (target: LessonStatus) => {
        const config = transitionButtons[target];

        if (
            config.confirm &&
            !(await confirm({
                title: `${config.label} a lição?`,
                description: config.confirm,
                confirmLabel: config.label,
            }))
        ) {
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
                <Breadcrumbs
                    items={[
                        { title: 'Painel', href: dashboard.url() },
                        { title: 'Lições', href: lessonsIndex.url() },
                        { title: lesson.title },
                    ]}
                />
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
                        <p className="mt-3 rounded-xl bg-warning-soft p-3 text-sm text-warning-foreground">
                            Rascunho: só professores da classe conseguem ver.
                            Publique quando estiver pronta para os alunos.
                        </p>
                    )}
                </header>

                <SectionNav
                    label="Seções da edição"
                    className="mb-8"
                    sections={[
                        { id: 'dados', label: 'Dados' },
                        { id: 'audio', label: 'Áudio' },
                        {
                            id: 'domingos',
                            label: `Domingos (${lesson.meetings.length})`,
                        },
                        {
                            id: 'blocos',
                            label: `Aprofundamento (${lesson.blocks.length})`,
                        },
                        {
                            id: 'leituras',
                            label: `Leituras (${lesson.readings.length})`,
                        },
                        {
                            id: 'materiais',
                            label: `Materiais (${lesson.materials.length})`,
                        },
                    ]}
                />

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
                        id="audio"
                        title="Áudio do estudo"
                        icon={<AudioLines />}
                        description="Narração do estudo para a classe ouvir no celular. Gerada uma vez; depois de editar o estudo, regenere."
                    >
                        <LessonAudioManager
                            lessonId={lesson.id}
                            audio={audio}
                        />
                    </Section>

                    <Section
                        id="domingos"
                        title="Domingos desta lição"
                        icon={<CalendarCheck />}
                        description="Uma lição pode ocupar mais de um domingo. As datas ficam em Domingos, na página da classe."
                    >
                        {lesson.meetings.length > 0 ? (
                            <ul className="divide-y rounded-2xl border bg-card">
                                {lesson.meetings.map((meeting, index) => (
                                    <li key={meeting.id}>
                                        <Link
                                            href={meeting.url}
                                            className="flex items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-muted/50"
                                        >
                                            <span className="first-letter:uppercase">
                                                {meeting.date_label}
                                                {lesson.meetings.length > 1 &&
                                                    ` · domingo ${index + 1} de ${lesson.meetings.length}`}
                                            </span>
                                            <MeetingStatusBadge
                                                status={meeting.status}
                                                label={meeting.status_label}
                                            />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="rounded-2xl border border-dashed p-4 text-sm text-muted-foreground">
                                Esta lição ainda não está em nenhum domingo.
                            </p>
                        )}
                        <Button asChild variant="outline" className="mt-3">
                            <Link href={lesson.meetings_url}>
                                <CalendarDays /> Abrir Domingos
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

                    <div className="border-t pt-6">
                        <Button
                            variant="ghost"
                            className="text-destructive hover:text-destructive"
                            onClick={async () => {
                                if (
                                    await confirm({
                                        title: 'Excluir esta lição?',
                                        description:
                                            'Ela sai do site e da biblioteca, junto com materiais, leituras e blocos. Não dá para desfazer.',
                                        confirmLabel: 'Excluir lição',
                                        destructive: true,
                                    })
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
