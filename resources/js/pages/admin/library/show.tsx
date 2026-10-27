import { Head, useForm } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import { Field } from '@/components/form-field';
import { RichText } from '@/components/lesson/rich-text';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes/admin';
import { copy, index } from '@/routes/admin/library';

type StudyLesson = {
    number: number | null;
    title: string;
    summary: string | null;
    bible_reference: string | null;
    key_verse: string | null;
    goal: string | null;
    content_html: string | null;
    blocks: { title: string | null; html: string | null }[];
    readings: string[];
};

type Study = {
    id: number;
    title: string;
    description: string | null;
    version: number;
    notes: string | null;
    lessons: StudyLesson[];
};

/**
 * Um estudo oficial para ler antes de usar, e "Usar numa turma".
 */
export default function LibraryShow({
    study,
    classrooms,
}: {
    study: Study;
    classrooms: { id: number; name: string }[];
}) {
    const form = useForm({ classroom_id: classrooms[0]?.id ?? null });

    return (
        <>
            <Head title={study.title} />
            <Page>
                <PageHeader
                    breadcrumbs={[
                        { title: 'Visão geral', href: dashboard.url() },
                        { title: 'Biblioteca oficial', href: index.url() },
                        { title: study.title },
                    ]}
                    title={study.title}
                    description={study.description ?? undefined}
                />

                <section className="mb-8 space-y-3 rounded-2xl border bg-card p-4 sm:p-5">
                    <p className="text-sm text-muted-foreground">
                        Versão {study.version} · {study.lessons.length}{' '}
                        {study.lessons.length === 1 ? 'lição' : 'lições'}. A
                        turma recebe uma cópia em rascunho: revise, ajuste e
                        publique quando quiser.
                    </p>
                    {classrooms.length === 0 ? (
                        <p className="text-sm">
                            Você ainda não gerencia nenhuma turma.
                        </p>
                    ) : (
                        <form
                            className="flex flex-wrap items-end gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(copy.url(study.id));
                            }}
                        >
                            <Field
                                label="Turma"
                                htmlFor="classroom_id"
                                error={form.errors.classroom_id}
                                className="min-w-48 flex-1"
                            >
                                <NativeSelect
                                    id="classroom_id"
                                    value={form.data.classroom_id ?? ''}
                                    onChange={(event) =>
                                        form.setData(
                                            'classroom_id',
                                            Number(event.target.value),
                                        )
                                    }
                                >
                                    {classrooms.map((classroom) => (
                                        <option
                                            key={classroom.id}
                                            value={classroom.id}
                                        >
                                            {classroom.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? <Spinner /> : <Copy />}
                                Usar nesta turma
                            </Button>
                        </form>
                    )}
                </section>

                <div className="space-y-4">
                    {study.lessons.map((lesson, i) => (
                        <details
                            key={i}
                            className="group rounded-2xl border bg-card p-4 sm:p-5"
                        >
                            <summary className="cursor-pointer list-none">
                                <span className="font-serif text-lg font-semibold">
                                    {lesson.number
                                        ? `Lição ${lesson.number} — ${lesson.title}`
                                        : lesson.title}
                                </span>
                                {lesson.bible_reference && (
                                    <span className="block text-sm text-muted-foreground">
                                        {lesson.bible_reference}
                                    </span>
                                )}
                            </summary>
                            <div className="mt-4 space-y-4">
                                {lesson.summary && (
                                    <p className="text-muted-foreground">
                                        {lesson.summary}
                                    </p>
                                )}
                                {lesson.content_html && (
                                    <RichText
                                        className="reading text-base"
                                        html={lesson.content_html}
                                    />
                                )}
                                {lesson.blocks.map(
                                    (block, j) =>
                                        block.html && (
                                            <div
                                                key={j}
                                                className="rounded-xl bg-muted/60 p-4"
                                            >
                                                {block.title && (
                                                    <p className="mb-1 font-medium">
                                                        {block.title}
                                                    </p>
                                                )}
                                                <RichText
                                                    className="reading text-sm"
                                                    html={block.html}
                                                />
                                            </div>
                                        ),
                                )}
                                {lesson.readings.length > 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Leituras da semana:{' '}
                                        {lesson.readings.join(' · ')}
                                    </p>
                                )}
                            </div>
                        </details>
                    ))}
                </div>
            </Page>
        </>
    );
}
