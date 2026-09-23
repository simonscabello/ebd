import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ReorderButtons } from '@/components/admin/reorder-buttons';
import { Field } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { destroy, store, update } from '@/routes/admin/lessons/readings';
import type { LessonReading, Option } from '@/types';

type Props = {
    lessonId: number;
    readings: LessonReading[];
    weekdays: Option<number>[];
};

type ReadingForm = { weekday: number | ''; reference: string; notes: string };

export function ReadingsManager({ lessonId, readings, weekdays }: Props) {
    const [editing, setEditing] = useState<number | null>(null);
    const ids = readings.map((r) => r.id);

    // Sugere o próximo dia ainda sem leitura.
    const usedDays = readings.map((r) => r.weekday);
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
                            className="flex items-start gap-2 p-3"
                        >
                            <ReorderButtons
                                lessonId={lessonId}
                                relation="readings"
                                ids={ids}
                                index={index}
                            />
                            {editing === reading.id ? (
                                <ReadingEditor
                                    lessonId={lessonId}
                                    weekdays={weekdays}
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
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => setEditing(reading.id)}
                                        aria-label="Editar leitura"
                                    >
                                        <Pencil />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Remover leitura"
                                        onClick={() => {
                                            if (
                                                confirm(
                                                    `Remover a leitura "${reading.reference}"?`,
                                                )
                                            ) {
                                                router.delete(
                                                    destroy.url({
                                                        lesson: lessonId,
                                                        reading: reading.id,
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

            <ReadingEditor
                key={`new-${readings.length}`}
                lessonId={lessonId}
                weekdays={weekdays}
                defaultDay={nextDay}
            />
        </div>
    );
}

function ReadingEditor({
    lessonId,
    weekdays,
    reading,
    defaultDay = '',
    onDone,
}: {
    lessonId: number;
    weekdays: Option<number>[];
    reading?: LessonReading;
    defaultDay?: number | '';
    onDone?: () => void;
}) {
    const form = useForm<ReadingForm>({
        weekday: reading?.weekday ?? defaultDay,
        reference: reading?.reference ?? '',
        notes: reading?.notes ?? '',
    });

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
            <div className="grid gap-3 sm:grid-cols-[11rem_1fr]">
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
            <div className="flex justify-end gap-2">
                {onDone && (
                    <Button type="button" variant="ghost" onClick={onDone}>
                        Cancelar
                    </Button>
                )}
                <Button
                    type="submit"
                    variant={reading ? 'default' : 'secondary'}
                    disabled={form.processing}
                >
                    {!reading && <Plus />}{' '}
                    {reading ? 'Salvar' : 'Adicionar leitura'}
                </Button>
            </div>
        </form>
    );
}
