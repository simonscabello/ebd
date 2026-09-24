import { useForm } from '@inertiajs/react';
import { Globe, Lock, Save } from 'lucide-react';
import { BiblePreview } from '@/components/admin/bible-preview';
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
    number: string;
    title: string;
    slug: string;
    meeting_on?: string;
    bible_reference: string;
    key_verse: string;
    goal: string;
    summary: string;
    content: string;
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
                delete payload.meeting_on;
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

            <div className="grid gap-6 sm:grid-cols-[8rem_1fr]">
                <Field
                    label="Nº na revista"
                    htmlFor="number"
                    error={errors.number}
                >
                    <Input
                        id="number"
                        type="number"
                        inputMode="numeric"
                        min={1}
                        max={999}
                        value={data.number}
                        onChange={(event) =>
                            setData('number', event.target.value)
                        }
                        placeholder="11"
                    />
                </Field>
                <Field label="Título" htmlFor="title" error={errors.title}>
                    <Input
                        id="title"
                        value={data.title}
                        onChange={(event) =>
                            setData('title', event.target.value)
                        }
                        required
                        maxLength={180}
                        placeholder="Ex.: É Necessário"
                    />
                </Field>
            </div>

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

            <div className="grid items-start gap-6 sm:grid-cols-2">
                {!editing && (
                    <Field
                        label="Domingo da aula"
                        htmlFor="meeting_on"
                        error={errors.meeting_on}
                        hint="Opcional. Depois é só ajustar pela agenda da classe."
                    >
                        <Input
                            id="meeting_on"
                            type="date"
                            value={data.meeting_on ?? ''}
                            onChange={(event) =>
                                setData('meeting_on', event.target.value)
                            }
                        />
                    </Field>
                )}
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
                        placeholder="Ex.: Jo 9.1-41"
                        maxLength={120}
                    />
                </Field>
            </div>
            <BiblePreview reference={data.bible_reference} />

            <Field
                label="Versículo-chave"
                htmlFor="key_verse"
                error={errors.key_verse}
            >
                <Textarea
                    id="key_verse"
                    value={data.key_verse}
                    onChange={(event) =>
                        setData('key_verse', event.target.value)
                    }
                    rows={2}
                    placeholder="“É necessário que façamos as obras daquele que me enviou…” (Jo 9.4-5)"
                />
            </Field>

            <Field label="Alvo da lição" htmlFor="goal" error={errors.goal}>
                <Textarea
                    id="goal"
                    value={data.goal}
                    onChange={(event) => setData('goal', event.target.value)}
                    rows={2}
                />
            </Field>

            <Field
                label="Resumo / introdução"
                htmlFor="summary"
                error={errors.summary}
                hint="Aparece no topo da lição."
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
                label="Estudo principal"
                htmlFor="content"
                error={errors.content}
                hint={
                    <>
                        Siga a revista: introdução, <code>## I. …</code>,{' '}
                        <code>### 1. …</code> e conclusão. Aceita Markdown (
                        <code>**negrito**</code>, <code>*itálico*</code>,{' '}
                        <code>&gt; citação</code>, listas). Os títulos viram os
                        tópicos do Modo Domingo. Roteiro, contexto, curiosidades
                        e conceitos entram como blocos, mais abaixo.
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
