import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ReorderButtons } from '@/components/admin/reorder-buttons';
import { Field } from '@/components/form-field';
import { MaterialIcon } from '@/components/lesson/material-card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { destroy, store, update } from '@/routes/admin/lessons/materials';
import type { LessonMaterial, MaterialTypeValue } from '@/types';

export type MaterialTypeOption = {
    value: MaterialTypeValue;
    label: string;
    accepts_upload: boolean;
    requires_upload: boolean;
    requires_url: boolean;
    accept: string;
    max_mb: number;
};

type Props = {
    lessonId: number;
    materials: LessonMaterial[];
    types: MaterialTypeOption[];
};

type MaterialForm = {
    type: MaterialTypeValue;
    title: string;
    description: string;
    url: string;
    is_primary: boolean;
    file: File | null;
};

export function MaterialsManager({ lessonId, materials, types }: Props) {
    const [editing, setEditing] = useState<number | null>(null);
    const ids = materials.map((m) => m.id);

    return (
        <div className="space-y-3">
            {materials.length > 0 && (
                <ul className="divide-y rounded-2xl border bg-card">
                    {materials.map((material, index) => (
                        <li
                            key={material.id}
                            className="flex items-start gap-2 p-3"
                        >
                            <ReorderButtons
                                lessonId={lessonId}
                                relation="materials"
                                ids={ids}
                                index={index}
                            />
                            {editing === material.id ? (
                                <MaterialEditor
                                    lessonId={lessonId}
                                    types={types}
                                    material={material}
                                    onDone={() => setEditing(null)}
                                />
                            ) : (
                                <>
                                    <span className="mt-1 flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                                        <MaterialIcon
                                            type={material.type}
                                            className="size-5"
                                        />
                                    </span>
                                    <div className="min-w-0 flex-1 py-0.5">
                                        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                            {material.is_primary && (
                                                <Star className="size-3.5 fill-current text-amber-500" />
                                            )}
                                            {material.type_label}
                                            {material.file &&
                                                ` · ${material.file.name} · ${material.file.size}`}
                                        </p>
                                        <p className="font-medium">
                                            {material.title}
                                        </p>
                                        {material.url && (
                                            <p className="truncate text-sm text-muted-foreground">
                                                {material.url}
                                            </p>
                                        )}
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => setEditing(material.id)}
                                        aria-label="Editar material"
                                    >
                                        <Pencil />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Remover material"
                                        onClick={() => {
                                            if (
                                                confirm(
                                                    `Remover "${material.title}"? Arquivos enviados também serão apagados.`,
                                                )
                                            ) {
                                                router.delete(
                                                    destroy.url({
                                                        lesson: lessonId,
                                                        material: material.id,
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

            <MaterialEditor
                key={`new-${materials.length}`}
                lessonId={lessonId}
                types={types}
            />
        </div>
    );
}

function MaterialEditor({
    lessonId,
    types,
    material,
    onDone,
}: {
    lessonId: number;
    types: MaterialTypeOption[];
    material?: LessonMaterial;
    onDone?: () => void;
}) {
    const form = useForm<MaterialForm>({
        type: material?.type ?? 'pdf',
        title: material?.title ?? '',
        description: material?.description ?? '',
        url: material?.url ?? '',
        is_primary: material?.is_primary ?? false,
        file: null,
    });
    const { data, setData, errors, processing, progress } = form;
    const type = types.find((t) => t.value === data.type) ?? types[0];
    const prefix = material ? `material-${material.id}` : 'material-new';
    const showUrl =
        !material?.file &&
        (type.requires_url ||
            type.value === 'audio' ||
            type.value === 'reference');

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((values) => {
            const payload: Record<string, unknown> = {
                title: values.title,
                description: values.description,
                url: values.url,
                is_primary: values.is_primary ? 1 : 0,
            };

            if (!material) payload.type = values.type;
            if (values.file) payload.file = values.file;

            return payload;
        });

        const target = material
            ? update({ lesson: lessonId, material: material.id })
            : store(lessonId);

        form.submit(target, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                if (!material) form.reset();
                onDone?.();
            },
        });
    };

    return (
        <form
            onSubmit={submit}
            className={
                material
                    ? 'flex-1 space-y-4'
                    : 'space-y-4 rounded-2xl border border-dashed p-4'
            }
        >
            {!material && (
                <fieldset>
                    <legend className="mb-2 text-sm font-medium">
                        Adicionar material
                    </legend>
                    <div className="flex flex-wrap gap-2">
                        {types.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                onClick={() =>
                                    setData((current) => ({
                                        ...current,
                                        type: option.value,
                                        file: null,
                                    }))
                                }
                                aria-pressed={data.type === option.value}
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-full border bg-card px-3 py-1.5 text-sm',
                                    data.type === option.value &&
                                        'border-primary bg-primary text-primary-foreground',
                                )}
                            >
                                <MaterialIcon
                                    type={option.value}
                                    className="size-4"
                                />
                                {option.label}
                            </button>
                        ))}
                    </div>
                    {errors.type && (
                        <p className="mt-1 text-sm text-destructive">
                            {errors.type}
                        </p>
                    )}
                </fieldset>
            )}

            <Field
                label="Título"
                htmlFor={`${prefix}-title`}
                error={errors.title}
            >
                <Input
                    id={`${prefix}-title`}
                    value={data.title}
                    onChange={(event) => setData('title', event.target.value)}
                    required
                    maxLength={180}
                    placeholder={
                        type.value === 'reference'
                            ? 'Ex.: O Conhecimento do Santo — A. W. Tozer'
                            : 'Ex.: Lição 5 (revista)'
                    }
                />
            </Field>

            {type.accepts_upload && (
                <Field
                    label={material?.file ? 'Substituir arquivo' : 'Arquivo'}
                    htmlFor={`${prefix}-file`}
                    error={errors.file}
                    hint={`Formatos: ${type.accept.replaceAll('.', '').replaceAll(',', ', ')}. Até ${type.max_mb} MB.${type.value === 'audio' ? ' Ou informe um link abaixo.' : ''}`}
                >
                    <Input
                        id={`${prefix}-file`}
                        type="file"
                        accept={type.accept}
                        onChange={(event) =>
                            setData('file', event.target.files?.[0] ?? null)
                        }
                        required={!material && type.requires_upload}
                        className="h-auto py-2"
                    />
                    {progress && (
                        <progress
                            value={progress.percentage}
                            max={100}
                            className="h-1.5 w-full overflow-hidden rounded-full accent-primary"
                        >
                            {progress.percentage}%
                        </progress>
                    )}
                </Field>
            )}

            {showUrl && (
                <Field
                    label={type.requires_url ? 'Link' : 'Link (opcional)'}
                    htmlFor={`${prefix}-url`}
                    error={errors.url}
                >
                    <Input
                        id={`${prefix}-url`}
                        type="url"
                        inputMode="url"
                        value={data.url}
                        onChange={(event) => setData('url', event.target.value)}
                        required={type.requires_url}
                        placeholder="https://"
                    />
                </Field>
            )}

            <Field
                label="Descrição (opcional)"
                htmlFor={`${prefix}-description`}
                error={errors.description}
            >
                <Textarea
                    id={`${prefix}-description`}
                    value={data.description}
                    onChange={(event) =>
                        setData('description', event.target.value)
                    }
                    rows={2}
                    className="min-h-16"
                />
            </Field>

            {type.value !== 'reference' && (
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={`${prefix}-primary`}
                        checked={data.is_primary}
                        onCheckedChange={(value) =>
                            setData('is_primary', value === true)
                        }
                    />
                    <Label
                        htmlFor={`${prefix}-primary`}
                        className="font-normal"
                    >
                        Material principal da lição (ex.: a revista)
                    </Label>
                </div>
            )}

            <div className="flex justify-end gap-2">
                {onDone && (
                    <Button type="button" variant="ghost" onClick={onDone}>
                        Cancelar
                    </Button>
                )}
                <Button
                    type="submit"
                    variant={material ? 'default' : 'secondary'}
                    disabled={processing}
                >
                    {!material && <Plus />}{' '}
                    {material ? 'Salvar' : 'Adicionar material'}
                </Button>
            </div>
        </form>
    );
}
