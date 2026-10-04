import { Head, router, useForm } from '@inertiajs/react';
import { GraduationCap, Save, UserMinus, UserPlus } from 'lucide-react';
import { useConfirm } from '@/components/confirm-dialog';
import { Field } from '@/components/form-field';
import { Page, PageHeader, Section } from '@/components/page';
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
import {
    destroy as removeMember,
    store as addMember,
} from '@/routes/admin/classrooms/members';
import type { Classroom } from '@/types';

type Teacher = { id: number; name: string; email: string | null };

export default function ClassroomForm({
    classroom,
    teachers = [],
}: {
    classroom: (Classroom & { position: number }) | null;
    teachers?: Teacher[];
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
                        { title: 'Painel', href: dashboard.url() },
                        { title: 'Classes', href: classroomsIndex.url() },
                        { title: classroom ? classroom.name : 'Nova' },
                    ]}
                    title={classroom ? 'Editar classe' : 'Nova classe'}
                />
                <form
                    noValidate
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

                {classroom && (
                    <Teachers classroom={classroom} teachers={teachers} />
                )}
            </Page>
        </>
    );
}

/**
 * Professores da classe: só a administração define quem dá aula.
 */
function Teachers({
    classroom,
    teachers,
}: {
    classroom: Classroom;
    teachers: Teacher[];
}) {
    const confirm = useConfirm();
    const form = useForm({ email: '', role: 'teacher' });

    const remove = async (teacher: Teacher) => {
        if (
            await confirm({
                title: `Tirar ${teacher.name} dos professores?`,
                description: `${teacher.name} deixa de fazer parte da classe ${classroom.name}.`,
                confirmLabel: 'Tirar',
                destructive: true,
            })
        ) {
            router.delete(
                removeMember.url({
                    classroom: classroom.slug,
                    user: teacher.id,
                }),
                { preserveScroll: true },
            );
        }
    };

    return (
        <Section
            title="Professores"
            icon={<GraduationCap />}
            className="mt-12"
            description="Quem dá aula nesta classe. A pessoa precisa ter conta com e-mail no app."
        >
            {teachers.length === 0 ? (
                <p className="rounded-2xl border border-dashed p-4 text-sm text-muted-foreground">
                    Nenhum professor ainda.
                </p>
            ) : (
                <ul className="divide-y rounded-2xl border bg-card">
                    {teachers.map((teacher) => (
                        <li
                            key={teacher.id}
                            className="flex items-center gap-3 px-4 py-3"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-medium">
                                    {teacher.name}
                                </p>
                                <p className="truncate text-sm text-muted-foreground">
                                    {teacher.email}
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => remove(teacher)}
                                aria-label={`Tirar ${teacher.name} dos professores`}
                            >
                                <UserMinus />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
            <form
                noValidate
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(addMember.url(classroom.slug), {
                        preserveScroll: true,
                        onSuccess: () => form.reset('email'),
                    });
                }}
                className="mt-4 flex flex-col gap-2 sm:flex-row sm:items-start"
            >
                <div className="flex-1">
                    <Field
                        label="E-mail do professor"
                        htmlFor="teacher-email"
                        error={form.errors.email}
                    >
                        <Input
                            id="teacher-email"
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            placeholder="professor@email.com"
                            required
                        />
                    </Field>
                </div>
                <Button
                    type="submit"
                    disabled={form.processing}
                    className="sm:mt-7"
                >
                    <UserPlus /> Adicionar professor
                </Button>
            </form>
        </Section>
    );
}
