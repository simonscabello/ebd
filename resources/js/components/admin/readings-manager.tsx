import { router, useForm } from '@inertiajs/react';
import { CircleAlert, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { BiblePreview } from '@/components/admin/bible-preview';
import { ReorderButtons } from '@/components/admin/reorder-buttons';
import { useConfirm } from '@/components/confirm-dialog';
import { UnsavedHint } from '@/components/admin/unsaved-hint';
import { Field } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useUnsavedChangesGuard } from '@/hooks/use-unsaved-changes-guard';
import { destroy, store, update } from '@/routes/admin/lessons/readings';
import type { LessonReading, Option } from '@/types';

type Props = {
    lessonId: number;
    /** Turma da lição: a prévia mostra o texto só se a igreja dela tem licença. */
    classroomId: number;
    readings: LessonReading[];
    weekdays: Option<number>[];
};

type ReadingForm = {
    week: number;
    weekday: number | '';
    reference: string;
    notes: string;
};

export function ReadingsManager({
    lessonId,
    classroomId,
    readings: unsorted,
    weekdays,
}: Props) {
    const confirm = useConfirm();
    const [editing, setEditing] = useState<number | null>(null);
    // Uma sequência por semana da lição; dentro dela, a ordem de sempre.
    const readings = [...unsorted].sort((a, b) => a.week - b.week);
    const ids = readings.map((r) => r.id);
    const lastWeek = Math.max(1, ...readings.map((r) => r.week));
    // As semanas que existem e mais uma, para abrir a seguinte.
    const weeks = Array.from({ length: lastWeek + 1 }, (_, i) => i + 1);

    // Sugere o próximo dia ainda sem leitura na última semana.
    const usedDays = readings
        .filter((r) => r.week === lastWeek)
        .map((r) => r.weekday);
    const nextDay =
        weekdays.find((d) => d.value !== 7 && !usedDays.includes(d.value))
            ?.value ?? '';

    return (
        <div className="space-y-3">
            {readings.length > 0 && (
                <ul className="divide-y rounded-2xl border bg-card">
                    {readings.map((reading, index) => (
                        <li
                            key={reading.id}
                            className="flex flex-wrap items-start gap-2 p-3"
                        >
                            {lastWeek > 1 &&
                                reading.week !== readings[index - 1]?.week && (
                                    <p className="w-full text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        {reading.week}ª semana da lição
                                    </p>
                                )}
                            <ReorderButtons
                                lessonId={lessonId}
                                relation="readings"
                                ids={ids}
                                index={index}
                            />
                            {editing === reading.id ? (
                                <ReadingEditor
                                    lessonId={lessonId}
                                    classroomId={classroomId}
                                    weekdays={weekdays}
                                    weeks={weeks}
                                    reading={reading}
                                    onDone={() => setEditing(null)}
                                />
                            ) : (
                                <>
                                    <div className="min-w-0 flex-1 py-1">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            {reading.weekday_label ??
                                                'Sem dia definido'}
                                        </p>
                                        <p className="font-medium">
                                            {reading.reference}
                                        </p>
                                        {reading.notes && (
                                            <p className="text-sm text-muted-foreground">
                                                {reading.notes}
                                            </p>
                                        )}
                                        {reading.passage ? (
                                            <p className="mt-1 line-clamp-1 font-serif text-sm text-muted-foreground">
                                                {reading.passage.verses[0].text}
                                            </p>
                                        ) : (
                                            <p className="mt-1 flex items-center gap-1 text-xs text-amber-700 dark:text-amber-400">
                                                <CircleAlert className="size-3.5" />
                                                Sem texto: referência não
                                                reconhecida.
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex shrink-0 flex-col gap-1.5 sm:flex-row">
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            onClick={() =>
                                                setEditing(reading.id)
                                            }
                                            aria-label="Editar leitura"
                                        >
                                            <Pencil />
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            aria-label="Remover leitura"
                                            onClick={async () => {
                                                if (
                                                    await confirm({
                                                        title: `Remover a leitura "${reading.reference}"?`,
                                                        confirmLabel: 'Remover',
                                                        destructive: true,
                                                    })
                                                ) {
                                                    router.delete(
                                                        destroy.url({
                                                            lesson: lessonId,
                                                            reading: reading.id,
                                                        }),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    );
                                                }
                                            }}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <ReadingEditor
                key={`new-${readings.length}`}
                lessonId={lessonId}
                classroomId={classroomId}
                weekdays={weekdays}
                weeks={weeks}
                defaultWeek={lastWeek}
                defaultDay={nextDay}
            />
            <p className="text-xs text-muted-foreground">
                Se a lição durar mais de um encontro, cada semana mostra a sua
                sequência de leituras. Sem sequência cadastrada para a semana, o
                app escreve uma nova sozinho (onde o “Tirar dúvida” está ligado)
                ou repete a última.
            </p>
        </div>
    );
}

function ReadingEditor({
    lessonId,
    classroomId,
    weekdays,
    weeks,
    reading,
    defaultWeek = 1,
    defaultDay = '',
    onDone,
}: {
    lessonId: number;
    classroomId: number;
    weekdays: Option<number>[];
    weeks: number[];
    reading?: LessonReading;
    defaultWeek?: number;
    defaultDay?: number | '';
    onDone?: () => void;
}) {
    const form = useForm<ReadingForm>({
        week: reading?.week ?? defaultWeek,
        weekday: reading?.weekday ?? defaultDay,
        reference: reading?.reference ?? '',
        notes: reading?.notes ?? '',
    });
    const unsaved =
        form.isDirty &&
        (reading !== undefined ||
            Boolean(form.data.reference || form.data.notes));

    useUnsavedChangesGuard(unsaved && !form.processing);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            weekday: data.weekday === '' ? null : data.weekday,
        }));

        const target = reading
            ? update({ lesson: lessonId, reading: reading.id })
            : store(lessonId);

        form.submit(target, {
            preserveScroll: true,
            onSuccess: () => {
                if (!reading) form.reset('reference', 'notes');
                onDone?.();
            },
        });
    };

    return (
        <form
            noValidate
            onSubmit={submit}
            className={
                reading
                    ? 'flex-1 space-y-3'
                    : 'space-y-3 rounded-2xl border border-dashed p-4'
            }
        >
            {!reading && (
                <p className="text-sm font-medium">Adicionar leitura</p>
            )}
            <div className="grid gap-3 sm:grid-cols-[8rem_11rem_1fr]">
                <Field
                    label="Semana"
                    htmlFor={`week-${reading?.id ?? 'new'}`}
                    error={form.errors.week}
                >
                    <NativeSelect
                        id={`week-${reading?.id ?? 'new'}`}
                        value={form.data.week}
                        onChange={(event) =>
                            form.setData('week', Number(event.target.value))
                        }
                    >
                        {weeks.map((week) => (
                            <option key={week} value={week}>
                                {week}ª semana
                            </option>
                        ))}
                    </NativeSelect>
                </Field>
                <Field
                    label="Dia"
                    htmlFor={`weekday-${reading?.id ?? 'new'}`}
                    error={form.errors.weekday}
                >
                    <NativeSelect
                        id={`weekday-${reading?.id ?? 'new'}`}
                        value={form.data.weekday}
                        onChange={(event) =>
                            form.setData(
                                'weekday',
                                event.target.value
                                    ? Number(event.target.value)
                                    : '',
                            )
                        }
                    >
                        <option value="">Sem dia</option>
                        {weekdays.map((day) => (
                            <option key={day.value} value={day.value}>
                                {day.label}
                            </option>
                        ))}
                    </NativeSelect>
                </Field>
                <Field
                    label="Leitura"
                    htmlFor={`reference-${reading?.id ?? 'new'}`}
                    error={form.errors.reference}
                >
                    <Input
                        id={`reference-${reading?.id ?? 'new'}`}
                        value={form.data.reference}
                        onChange={(event) =>
                            form.setData('reference', event.target.value)
                        }
                        placeholder="Ex.: Isaías 6:1-8"
                        required
                    />
                </Field>
            </div>
            <BiblePreview
                reference={form.data.reference}
                classroomId={classroomId}
            />
            <Field
                label="Orientação (opcional)"
                htmlFor={`notes-${reading?.id ?? 'new'}`}
                error={form.errors.notes}
            >
                <Textarea
                    id={`notes-${reading?.id ?? 'new'}`}
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                    rows={2}
                    className="min-h-16"
                />
            </Field>
            <div className="flex items-center justify-end gap-2">
                {unsaved && <UnsavedHint />}
                {onDone && (
                    <Button type="button" variant="outline" onClick={onDone}>
                        Cancelar
                    </Button>
                )}
                <Button
                    type="submit"
                    variant={reading ? 'default' : 'outline'}
                    disabled={form.processing}
                >
                    {!reading && <Plus />}{' '}
                    {reading ? 'Salvar' : 'Adicionar leitura'}
                </Button>
            </div>
        </form>
    );
}
