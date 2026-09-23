import { useForm } from '@inertiajs/react';
import { Globe, Lock, Save } from 'lucide-react';
import { Field } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { store, update } from '@/routes/admin/lessons';
import type { Classroom, Option, Series } from '@/types';

export type LessonFormData = {
    classroom_id: number | null;
    series_id: number | null;
    title: string;
    slug: string;
    scheduled_for: string;
    bible_reference: string;
    bible_text: string;
    summary: string;
    content: string;
    teacher_notes: string;
    visibility: 'public' | 'members';
    author_ids: number[];
};

type Props = {
    lessonId?: number;
    initial: LessonFormData;
    classrooms?: Classroom[];
    series: Series[];
    visibilities: Option[];
    authors?: { id: number; name: string }[];
    slugLocked?: boolean;
};

/**
 * Formulário dos dados da lição (criação e edição).
 */
export function LessonForm({
    lessonId,
    initial,
    classrooms,
    series,
    visibilities,
    authors,
    slugLocked = false,
}: Props) {
    const editing = lessonId !== undefined;
    const form = useForm<LessonFormData>(initial);
    const { data, setData, errors, processing, isDirty } = form;

    const availableSeries = series.filter(
        (item) =>
            data.classroom_id === null ||
            item.classroom_id === data.classroom_id,
    );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((values) => {
            const payload: Partial<LessonFormData> = { ...values };

            if (editing) {
                delete payload.classroom_id;
            } else {
                delete payload.slug;
                delete payload.author_ids;
            }

            return payload;
        });

        if (editing) {
            form.submit(update(lessonId), { preserveScroll: true });
        } else {
            form.submit(store());
        }
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            {!editing && classrooms && (
                <Field
                    label="Classe"
                    htmlFor="classroom_id"
                    error={errors.classroom_id}
                >
                    <NativeSelect
                        id="classroom_id"
                        value={data.classroom_id ?? ''}
                        onChange={(event) => {
                            setData((current) => ({
                                ...current,
                                classroom_id: event.target.value
                                    ? Number(event.target.value)
                                    : null,
                                series_id: null,
                            }));
                        }}
                        required
                    >
                        {classrooms.map((classroom) => (
                            <option key={classroom.id} value={classroom.id}>
                                {classroom.name}
                            </option>
                        ))}
                    </NativeSelect>
                </Field>
            )}

            <Field
                label="Série"
                htmlFor="series_id"
                error={errors.series_id}
                hint="Opcional. Lições avulsas também são permitidas."
            >
                <NativeSelect
                    id="series_id"
                    value={data.series_id ?? ''}
                    onChange={(event) =>
                        setData(
                            'series_id',
                            event.target.value
                                ? Number(event.target.value)
                                : null,
                        )
                    }
                >
                    <option value="">Sem série (lição avulsa)</option>
                    {availableSeries.map((item) => (
                        <option key={item.id} value={item.id}>
                            {item.title}
                        </option>
                    ))}
                </NativeSelect>
            </Field>

            <Field label="Título" htmlFor="title" error={errors.title}>
                <Input
                    id="title"
                    value={data.title}
                    onChange={(event) => setData('title', event.target.value)}
                    required
                    maxLength={180}
                    placeholder="Ex.: A Santidade de Deus"
                />
            </Field>

            {editing && (
                <Field
                    label="Endereço da lição"
                    htmlFor="slug"
                    error={errors.slug}
                    hint={
                        slugLocked
                            ? 'A lição já foi publicada: o endereço não muda para não quebrar links compartilhados.'
                            : 'Aparece no link: /licoes/endereco-da-licao'
                    }
                >
                    <Input
                        id="slug"
                        value={data.slug}
                        onChange={(event) =>
                            setData('slug', event.target.value)
                        }
                        disabled={slugLocked}
                        maxLength={200}
                    />
                </Field>
            )}

            <div className="grid gap-6 sm:grid-cols-2">
                <Field
                    label="Data da aula"
                    htmlFor="scheduled_for"
                    error={errors.scheduled_for}
                    hint="Necessária para publicar."
                >
                    <Input
                        id="scheduled_for"
                        type="date"
                        value={data.scheduled_for}
                        onChange={(event) =>
                            setData('scheduled_for', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Texto bíblico principal"
                    htmlFor="bible_reference"
                    error={errors.bible_reference}
                >
                    <Input
                        id="bible_reference"
                        value={data.bible_reference}
                        onChange={(event) =>
                            setData('bible_reference', event.target.value)
                        }
                        placeholder="Ex.: Lucas 5:1–11"
                        maxLength={120}
                    />
                </Field>
            </div>

            <Field
                label="Versículos em destaque"
                htmlFor="bible_text"
                error={errors.bible_text}
                hint="Opcional. Trechos do texto base para exibir junto da referência."
            >
                <Textarea
                    id="bible_text"
                    value={data.bible_text}
                    onChange={(event) =>
                        setData('bible_text', event.target.value)
                    }
                    rows={3}
                />
            </Field>

            <Field
                label="Resumo / introdução"
                htmlFor="summary"
                error={errors.summary}
                hint="Aparece no topo da lição e na página inicial."
            >
                <Textarea
                    id="summary"
                    value={data.summary}
                    onChange={(event) => setData('summary', event.target.value)}
                    rows={3}
                    maxLength={2000}
                />
            </Field>

            <Field
                label="Conteúdo do estudo"
                htmlFor="content"
                error={errors.content}
                hint={
                    <>
                        Aceita Markdown: <code>## Título de seção</code>,{' '}
                        <code>**negrito**</code>, <code>*itálico*</code>,{' '}
                        <code>&gt; citação</code> e listas. Os títulos{' '}
                        <code>##</code> viram os tópicos do Modo Domingo.
                    </>
                }
            >
                <Textarea
                    id="content"
                    value={data.content}
                    onChange={(event) => setData('content', event.target.value)}
                    rows={14}
                    className="font-mono text-sm"
                />
            </Field>

            <Field
                label="Notas do professor"
                htmlFor="teacher_notes"
                error={errors.teacher_notes}
                hint="Visíveis apenas para professores da classe (inclusive no Modo Domingo)."
            >
                <Textarea
                    id="teacher_notes"
                    value={data.teacher_notes}
                    onChange={(event) =>
                        setData('teacher_notes', event.target.value)
                    }
                    rows={5}
                    className="font-mono text-sm"
                />
            </Field>

            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm font-medium">
                    Quem pode ver a lição publicada
                </legend>
                <div className="grid gap-2 sm:grid-cols-2">
                    {visibilities.map((option) => (
                        <label
                            key={option.value}
                            className={cn(
                                'flex cursor-pointer items-start gap-3 rounded-xl border bg-card p-3.5 text-sm',
                                data.visibility === option.value &&
                                    'border-primary ring-1 ring-primary',
                            )}
                        >
                            <input
                                type="radio"
                                name="visibility"
                                value={option.value}
                                checked={data.visibility === option.value}
                                onChange={() =>
                                    setData(
                                        'visibility',
                                        option.value as LessonFormData['visibility'],
                                    )
                                }
                                className="sr-only"
                            />
                            {option.value === 'public' ? (
                                <Globe className="mt-0.5 size-4 text-primary" />
                            ) : (
                                <Lock className="mt-0.5 size-4 text-primary" />
                            )}
                            <span>{option.label}</span>
                        </label>
                    ))}
                </div>
            </fieldset>

            {editing && authors && authors.length > 0 && (
                <fieldset className="grid gap-2">
                    <legend className="mb-2 text-sm font-medium">
                        Professores responsáveis
                    </legend>
                    <div className="flex flex-wrap gap-2">
                        {authors.map((author) => {
                            const checked = data.author_ids.includes(author.id);

                            return (
                                <label
                                    key={author.id}
                                    className="flex items-center gap-2 rounded-lg border bg-card px-3 py-2 text-sm"
                                >
                                    <Checkbox
                                        checked={checked}
                                        onCheckedChange={(value) =>
                                            setData(
                                                'author_ids',
                                                value
                                                    ? [
                                                          ...data.author_ids,
                                                          author.id,
                                                      ]
                                                    : data.author_ids.filter(
                                                          (id) =>
                                                              id !== author.id,
                                                      ),
                                            )
                                        }
                                    />
                                    {author.name}
                                </label>
                            );
                        })}
                    </div>
                </fieldset>
            )}

            <div
                className={cn(
                    'flex items-center justify-end gap-3',
                    (!editing || isDirty) &&
                        'sticky bottom-20 z-10 -mx-4 border-t bg-background/95 px-4 py-3 backdrop-blur md:bottom-0',
                )}
            >
                {editing && isDirty && (
                    <span className="text-sm text-muted-foreground">
                        Alterações não salvas
                    </span>
                )}
                <Button
                    type="submit"
                    disabled={processing || (editing && !isDirty)}
                >
                    {processing ? <Spinner /> : <Save />}
                    {editing ? 'Salvar alterações' : 'Criar lição'}
                </Button>
            </div>
        </form>
    );
}
