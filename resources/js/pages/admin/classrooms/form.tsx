import { Head, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { Field } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes/admin';
import {
    index as classroomsIndex,
    store,
    update,
} from '@/routes/admin/classrooms';
import type { Classroom } from '@/types';

export default function ClassroomForm({
    classroom,
}: {
    classroom: (Classroom & { position: number }) | null;
}) {
    const form = useForm({
        name: classroom?.name ?? '',
        description: classroom?.description ?? '',
        is_active: classroom?.is_active ?? true,
        position: classroom?.position ?? 0,
    });
    const { data, setData, errors, processing } = form;

    return (
        <>
            <Head title={classroom ? 'Editar classe' : 'Nova classe'} />
            <Page>
                <PageHeader
                    breadcrumbs={[
                        { title: 'Gestão', href: dashboard.url() },
                        { title: 'Classes', href: classroomsIndex.url() },
                        { title: classroom ? classroom.name : 'Nova' },
                    ]}
                    title={classroom ? 'Editar classe' : 'Nova classe'}
                />
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.submit(
                            classroom ? update(classroom.slug) : store(),
                        );
                    }}
                    className="space-y-6"
                >
                    <Field label="Nome" htmlFor="name" error={errors.name}>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            maxLength={100}
                        />
                    </Field>
                    <Field
                        label="Descrição"
                        htmlFor="description"
                        error={errors.description}
                        hint="Ex.: horário e local da classe."
                    >
                        <Textarea
                            id="description"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            rows={3}
                        />
                    </Field>
                    <Field
                        label="Ordem de exibição"
                        htmlFor="position"
                        error={errors.position}
                    >
                        <Input
                            id="position"
                            type="number"
                            min={0}
                            value={data.position}
                            onChange={(e) =>
                                setData('position', Number(e.target.value))
                            }
                            className="w-32"
                        />
                    </Field>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="is_active"
                            checked={data.is_active}
                            onCheckedChange={(v) =>
                                setData('is_active', v === true)
                            }
                        />
                        <Label htmlFor="is_active" className="font-normal">
                            Classe ativa
                        </Label>
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            <Save /> Salvar
                        </Button>
                    </div>
                </form>
            </Page>
        </>
    );
}
