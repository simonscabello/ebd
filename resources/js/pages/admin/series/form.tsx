import { Head, router, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useConfirm } from '@/components/confirm-dialog';
import { Field } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes/admin';
import {
    destroy,
    index as seriesIndex,
    store,
    update,
} from '@/routes/admin/series';
import type { Classroom, Series } from '@/types';

type Props = {
    series: Series | null;
    classrooms: Classroom[];
    defaultClassroomId: number | null;
};

export default function SeriesForm({
    series,
    classrooms,
    defaultClassroomId,
}: Props) {
    const confirm = useConfirm();
    const form = useForm({
        classroom_id: defaultClassroomId ?? classrooms[0]?.id ?? null,
        title: series?.title ?? '',
        description: series?.description ?? '',
        starts_on: series?.starts_on ?? '',
        ends_on: series?.ends_on ?? '',
    });
    const { data, setData, errors, processing } = form;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (series) {
            form.transform(({ classroom_id: _classroom, ...rest }) => rest);
            form.submit(update(series.id));
        } else {
            form.submit(store());
        }
    };

    return (
        <>
            <Head title={series ? 'Editar série' : 'Nova série'} />
            <Page>
                <PageHeader
                    breadcrumbs={[
                        { title: 'Gestão', href: dashboard.url() },
                        { title: 'Séries', href: seriesIndex.url() },
                        { title: series ? 'Editar' : 'Nova' },
                    ]}
                    title={series ? 'Editar série' : 'Nova série'}
                    description={
                        series
                            ? `Classe ${series.classroom?.name}`
                            : 'Ex.: "Jornada dos Milagres de Jesus", "Filipenses".'
                    }
                />

                <form onSubmit={submit} className="space-y-6">
                    {!series && (
                        <Field
                            label="Classe"
                            htmlFor="classroom_id"
                            error={errors.classroom_id}
                        >
                            <NativeSelect
                                id="classroom_id"
                                value={data.classroom_id ?? ''}
                                onChange={(event) =>
                                    setData(
                                        'classroom_id',
                                        Number(event.target.value),
                                    )
                                }
                                required
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
                    )}

                    <Field label="Título" htmlFor="title" error={errors.title}>
                        <Input
                            id="title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            required
                            maxLength={160}
                        />
                    </Field>

                    <Field
                        label="Descrição"
                        htmlFor="description"
                        error={errors.description}
                    >
                        <Textarea
                            id="description"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            rows={4}
                        />
                    </Field>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <Field
                            label="Início"
                            htmlFor="starts_on"
                            error={errors.starts_on}
                        >
                            <Input
                                id="starts_on"
                                type="date"
                                value={data.starts_on}
                                onChange={(e) =>
                                    setData('starts_on', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Término"
                            htmlFor="ends_on"
                            error={errors.ends_on}
                        >
                            <Input
                                id="ends_on"
                                type="date"
                                value={data.ends_on}
                                onChange={(e) =>
                                    setData('ends_on', e.target.value)
                                }
                            />
                        </Field>
                    </div>

                    <div className="flex items-center justify-between gap-3">
                        {series ? (
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-destructive hover:text-destructive"
                                onClick={async () => {
                                    if (
                                        await confirm({
                                            title: 'Excluir esta série?',
                                            description:
                                                'Só é possível quando ela não tem lições.',
                                            confirmLabel: 'Excluir',
                                            destructive: true,
                                        })
                                    ) {
                                        router.delete(destroy.url(series.id));
                                    }
                                }}
                            >
                                <Trash2 /> Excluir
                            </Button>
                        ) : (
                            <span />
                        )}
                        <Button type="submit" disabled={processing}>
                            {processing ? <Spinner /> : <Save />}
                            {series ? 'Salvar' : 'Criar série'}
                        </Button>
                    </div>
                    {'series' in errors && (
                        <p className="text-sm text-destructive">
                            {(errors as Record<string, string>).series}
                        </p>
                    )}
                </form>
            </Page>
        </>
    );
}
