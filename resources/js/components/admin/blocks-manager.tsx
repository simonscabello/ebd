import { router, useForm } from '@inertiajs/react';
import { Lock, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ReorderButtons } from '@/components/admin/reorder-buttons';
import { Field } from '@/components/form-field';
import { BlockIcon } from '@/components/lesson/block-icon';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { destroy, store, update } from '@/routes/admin/lessons/blocks';
import type { Audience, LessonBlock, LessonBlockKind, Option } from '@/types';

export type BlockKindOption = {
    value: LessonBlockKind;
    label: string;
    default_audience: Audience;
};

type Props = {
    lessonId: number;
    blocks: LessonBlock[];
    kinds: BlockKindOption[];
    weekdays: Option<number>[];
};

type BlockForm = {
    kind: LessonBlockKind;
    audience: Audience;
    title: string;
    body: string;
    drip_weekday: number | '';
};

/**
 * Blocos de aprofundamento: roteiro, contexto histórico, teologia,
 * curiosidades, conceitos... Cada um é do professor ou dos alunos.
 */
export function BlocksManager({ lessonId, blocks, kinds, weekdays }: Props) {
    const [editing, setEditing] = useState<number | null>(null);
    const ids = blocks.map((b) => b.id);

    return (
        <div className="space-y-3">
            {blocks.length > 0 && (
                <ul className="divide-y rounded-2xl border bg-card">
                    {blocks.map((block, index) => (
                        <li
                            key={block.id}
                            className="flex items-start gap-2 p-3"
                        >
                            <ReorderButtons
                                lessonId={lessonId}
                                relation="blocks"
                                ids={ids}
                                index={index}
                            />
                            {editing === block.id ? (
                                <BlockEditor
                                    lessonId={lessonId}
                                    block={block}
                                    kinds={kinds}
                                    weekdays={weekdays}
                                    onDone={() => setEditing(null)}
                                />
                            ) : (
                                <>
                                    <span className="mt-1 flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                                        <BlockIcon
                                            kind={block.kind}
                                            className="size-5"
                                        />
                                    </span>
                                    <div className="min-w-0 flex-1 py-0.5">
                                        <p className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                            {block.kind_label}
                                            {block.audience === 'teacher' && (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                                    <Lock className="size-3" />{' '}
                                                    Só professor
                                                </span>
                                            )}
                                            {block.drip_weekday_label && (
                                                <span>
                                                    · Minha semana:{' '}
                                                    {block.drip_weekday_label}
                                                </span>
                                            )}
                                        </p>
                                        <p className="font-medium">
                                            {block.display_title}
                                        </p>
                                        <p className="line-clamp-2 text-sm text-muted-foreground">
                                            {block.body}
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => setEditing(block.id)}
                                        aria-label="Editar bloco"
                                    >
                                        <Pencil />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Remover bloco"
                                        onClick={() => {
                                            if (
                                                confirm('Remover este bloco?')
                                            ) {
                                                router.delete(
                                                    destroy.url({
                                                        lesson: lessonId,
                                                        block: block.id,
                                                    }),
                                                    { preserveScroll: true },
                                                );
                                            }
                                        }}
                                    >
                                        <Trash2 />
                                    </Button>
                                </>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <BlockEditor
                key={`new-${blocks.length}`}
                lessonId={lessonId}
                kinds={kinds}
                weekdays={weekdays}
            />
        </div>
    );
}

function BlockEditor({
    lessonId,
    block,
    kinds,
    weekdays,
    onDone,
}: {
    lessonId: number;
    block?: LessonBlock;
    kinds: BlockKindOption[];
    weekdays: Option<number>[];
    onDone?: () => void;
}) {
    const form = useForm<BlockForm>({
        kind: block?.kind ?? 'context',
        audience: block?.audience ?? 'student',
        title: block?.title ?? '',
        body: block?.body ?? '',
        drip_weekday: block?.drip_weekday ?? '',
    });
    const { data, setData, errors, processing } = form;
    const prefix = block ? `block-${block.id}` : 'block-new';

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((values) => ({
            ...values,
            drip_weekday:
                values.audience === 'student' && values.drip_weekday !== ''
                    ? values.drip_weekday
                    : null,
        }));

        const target = block
            ? update({ lesson: lessonId, block: block.id })
            : store(lessonId);

        form.submit(target, {
            preserveScroll: true,
            onSuccess: () => {
                if (!block) form.reset();
                onDone?.();
            },
        });
    };

    return (
        <form
            onSubmit={submit}
            className={
                block
                    ? 'flex-1 space-y-4'
                    : 'space-y-4 rounded-2xl border border-dashed p-4'
            }
        >
            {!block && <p className="text-sm font-medium">Novo bloco</p>}

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Tipo"
                    htmlFor={`${prefix}-kind`}
                    error={errors.kind}
                >
                    <NativeSelect
                        id={`${prefix}-kind`}
                        value={data.kind}
                        onChange={(event) => {
                            const kind = kinds.find(
                                (k) => k.value === event.target.value,
                            );

                            if (kind) {
                                setData((current) => ({
                                    ...current,
                                    kind: kind.value,
                                    audience: kind.default_audience,
                                }));
                            }
                        }}
                    >
                        {kinds.map((kind) => (
                            <option key={kind.value} value={kind.value}>
                                {kind.label}
                            </option>
                        ))}
                    </NativeSelect>
                </Field>
                <Field
                    label="Título (opcional)"
                    htmlFor={`${prefix}-title`}
                    error={errors.title}
                >
                    <Input
                        id={`${prefix}-title`}
                        value={data.title}
                        onChange={(event) =>
                            setData('title', event.target.value)
                        }
                        maxLength={180}
                        placeholder="Ex.: A piscina de Siloé existe"
                    />
                </Field>
            </div>

            <fieldset className="grid gap-2">
                <legend className="mb-1 text-sm font-medium">Quem vê</legend>
                <div className="grid grid-cols-2 gap-2">
                    {(
                        [
                            ['student', 'Alunos'],
                            ['teacher', 'Só professor'],
                        ] as const
                    ).map(([value, label]) => (
                        <label
                            key={value}
                            className={cn(
                                'flex cursor-pointer items-center gap-2 rounded-xl border bg-card p-3 text-sm',
                                data.audience === value &&
                                    'border-primary ring-1 ring-primary',
                            )}
                        >
                            <input
                                type="radio"
                                name={`${prefix}-audience`}
                                value={value}
                                checked={data.audience === value}
                                onChange={() => setData('audience', value)}
                                className="sr-only"
                            />
                            {value === 'teacher' && (
                                <Lock className="size-4 text-primary" />
                            )}
                            {label}
                        </label>
                    ))}
                </div>
            </fieldset>

            <Field
                label="Conteúdo"
                htmlFor={`${prefix}-body`}
                error={errors.body}
                hint="Aceita Markdown (### subtítulo, **negrito**, *itálico*, listas)."
            >
                <Textarea
                    id={`${prefix}-body`}
                    value={data.body}
                    onChange={(event) => setData('body', event.target.value)}
                    rows={block ? 10 : 6}
                    className="font-mono text-sm"
                    required
                />
            </Field>

            {data.audience === 'student' && (
                <Field
                    label="Liberar em “Minha semana”"
                    htmlFor={`${prefix}-drip`}
                    error={errors.drip_weekday}
                    hint="Opcional. Curiosidades e conceitos sem dia são distribuídos automaticamente entre segunda e sábado."
                >
                    <NativeSelect
                        id={`${prefix}-drip`}
                        value={data.drip_weekday}
                        onChange={(event) =>
                            setData(
                                'drip_weekday',
                                event.target.value
                                    ? Number(event.target.value)
                                    : '',
                            )
                        }
                    >
                        <option value="">Automático</option>
                        {weekdays.map((day) => (
                            <option key={day.value} value={day.value}>
                                {day.label}
                            </option>
                        ))}
                    </NativeSelect>
                </Field>
            )}

            <div className="flex justify-end gap-2">
                {onDone && (
                    <Button type="button" variant="ghost" onClick={onDone}>
                        Cancelar
                    </Button>
                )}
                <Button
                    type="submit"
                    variant={block ? 'default' : 'secondary'}
                    disabled={processing}
                >
                    {!block && <Plus />} {block ? 'Salvar' : 'Adicionar bloco'}
                </Button>
            </div>
        </form>
    );
}
