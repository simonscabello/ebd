import { useForm } from '@inertiajs/react';
import { Wand2 } from 'lucide-react';
import { useState } from 'react';
import { Field } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { plan } from '@/routes/admin/classrooms/meetings';
import type { Classroom, Series } from '@/types';

/**
 * "Planejar trimestre": cria os domingos do período e distribui as lições da
 * série pela numeração da revista, sem mexer no que já está na agenda.
 */
export function PlanMeetingsDialog({
    classroom,
    series,
    nextSunday,
}: {
    classroom: Classroom;
    series: Series[];
    nextSunday: string;
}) {
    const [open, setOpen] = useState(false);
    const current = series[0];
    const form = useForm<{ from: string; to: string; series_id: number | '' }>({
        from: current?.starts_on ?? nextSunday,
        to: current?.ends_on ?? '',
        series_id: current?.id ?? '',
    });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Wand2 /> Planejar trimestre
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((values) => ({
                            ...values,
                            series_id: values.series_id || null,
                        }));
                        form.submit(plan(classroom.slug), {
                            preserveScroll: true,
                            onSuccess: () => setOpen(false),
                        });
                    }}
                    className="space-y-4"
                >
                    <DialogHeader>
                        <DialogTitle>Planejar trimestre</DialogTitle>
                        <DialogDescription>
                            Cria um domingo para cada semana do período e
                            distribui as lições da série (pela numeração da
                            revista) nos domingos livres. Nada que já está na
                            agenda é alterado.
                        </DialogDescription>
                    </DialogHeader>
                    <Field
                        label="Série / revista"
                        htmlFor="plan-series"
                        error={form.errors.series_id}
                    >
                        <NativeSelect
                            id="plan-series"
                            value={form.data.series_id}
                            onChange={(event) => {
                                const chosen = series.find(
                                    (s) => s.id === Number(event.target.value),
                                );
                                form.setData((data) => ({
                                    ...data,
                                    series_id: chosen?.id ?? '',
                                    from: chosen?.starts_on ?? data.from,
                                    to: chosen?.ends_on ?? data.to,
                                }));
                            }}
                        >
                            <option value="">Só criar os domingos</option>
                            {series.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.title}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field
                            label="De"
                            htmlFor="plan-from"
                            error={form.errors.from}
                        >
                            <Input
                                id="plan-from"
                                type="date"
                                value={form.data.from}
                                onChange={(event) =>
                                    form.setData('from', event.target.value)
                                }
                                required
                            />
                        </Field>
                        <Field
                            label="Até"
                            htmlFor="plan-to"
                            error={form.errors.to}
                        >
                            <Input
                                id="plan-to"
                                type="date"
                                value={form.data.to}
                                onChange={(event) =>
                                    form.setData('to', event.target.value)
                                }
                                required
                            />
                        </Field>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Planejar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
