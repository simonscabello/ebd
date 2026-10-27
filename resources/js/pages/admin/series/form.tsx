import {
    lessons as classroomLessons,
    show as classroomPage,
} from '@/routes/admin/classrooms';
import { Head, Link, router, useForm } from '@inertiajs/react';
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
import { changes } from '@/routes/admin/series';
import { store as storeVersion } from '@/routes/admin/series/versions';
import type { Classroom, Series } from '@/types';

type OfficialVersions = {
    next_version: number;
    versions: { version: number; notes: string | null; created_at: string }[];
};

type Props = {
    series: Series | null;
    classrooms: Classroom[];
    defaultClassroomId: number | null;
    /** Estudo da biblioteca oficial, visto pelo dono. */
    official: OfficialVersions | null;
    /** Cópia de estudo oficial com versão nova publicada. */
    update_available: { from: number; to: number } | null;
};

export default function SeriesForm({
    series,
    classrooms,
    defaultClassroomId,
    official,
    update_available,
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
                    breadcrumbs={
                        series?.classroom
                            ? [
                                  {
                                      title: series.classroom.name,
                                      href: classroomPage.url(
                                          series.classroom.slug,
                                      ),
                                  },
                                  {
                                      title: 'Lições',
                                      href: classroomLessons.url(
                                          series.classroom.slug,
                                      ),
                                  },
                                  { title: 'Editar série' },
                              ]
                            : [
                                  {
                                      title: 'Visão geral',
                                      href: dashboard.url(),
                                  },
                                  { title: 'Séries', href: seriesIndex.url() },
                                  { title: series ? 'Editar' : 'Nova' },
                              ]
                    }
                    title={series ? 'Editar série' : 'Nova série'}
                    description={
                        series
                            ? `Classe ${series.classroom?.name}`
                            : 'Ex.: "Jornada dos Milagres de Jesus", "Filipenses".'
                    }
                />

                <form noValidate onSubmit={submit} className="space-y-6">
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
                                variant="outline"
                                className="border-destructive/30 text-destructive hover:bg-destructive/10 hover:text-destructive"
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

                {series && update_available && (
                    <UpdateNotice
                        seriesId={series.id}
                        update={update_available}
                    />
                )}

                {series && official && (
                    <OfficialLibraryVersions
                        seriesId={series.id}
                        official={official}
                    />
                )}
            </Page>
        </>
    );
}

/**
 * Biblioteca oficial: publicar a próxima versão (o retrato que as igrejas
 * copiam) e ver as anteriores. Só as lições publicadas entram.
 */
function OfficialLibraryVersions({
    seriesId,
    official,
}: {
    seriesId: number;
    official: OfficialVersions;
}) {
    const form = useForm({ notes: '' });

    return (
        <section className="mt-10 space-y-4 rounded-2xl border bg-card p-4 sm:p-5">
            <div>
                <h2 className="font-serif text-lg font-semibold">
                    Biblioteca oficial
                </h2>
                <p className="text-sm text-muted-foreground">
                    Publicar uma versão tira um retrato das lições publicadas.
                    As igrejas copiam a versão mais recente; quem já copiou vê o
                    aviso de versão nova. A cópia leva também os blocos e
                    materiais do professor: não cole versículos da NAA nem
                    trechos da revista.
                </p>
            </div>

            <form
                className="space-y-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(storeVersion.url(seriesId), {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <Field
                    label="O que mudou (opcional)"
                    htmlFor="version-notes"
                    error={
                        form.errors.notes ??
                        (form.errors as Record<string, string>).version
                    }
                >
                    <Textarea
                        id="version-notes"
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                        rows={2}
                    />
                </Field>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Publicar versão {official.next_version}
                </Button>
            </form>

            {official.versions.length > 0 && (
                <ul className="divide-y text-sm">
                    {official.versions.map((item) => (
                        <li key={item.version} className="py-2">
                            <span className="font-medium">
                                Versão {item.version}
                            </span>{' '}
                            <span className="text-muted-foreground">
                                · {item.created_at}
                            </span>
                            {item.notes && (
                                <p className="text-muted-foreground">
                                    {item.notes}
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

/** Aviso de versão nova do estudo oficial de onde a série foi copiada. */
function UpdateNotice({
    seriesId,
    update,
}: {
    seriesId: number;
    update: { from: number; to: number };
}) {
    return (
        <section className="mt-8 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-primary/30 bg-primary/5 p-4">
            <p className="text-sm">
                <span className="font-medium">Versão nova disponível.</span>{' '}
                Esta série veio da versão {update.from} do estudo oficial; já
                saiu a {update.to}.
            </p>
            <Button asChild variant="outline" size="sm">
                <Link href={changes(seriesId)}>Ver o que mudou</Link>
            </Button>
        </section>
    );
}
