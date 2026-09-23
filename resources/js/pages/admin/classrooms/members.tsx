import { Head, router, useForm } from '@inertiajs/react';
import { UserMinus, UserPlus } from 'lucide-react';
import { Field } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { destroy, store } from '@/routes/admin/classrooms/members';
import type { Classroom } from '@/types';

type Member = {
    id: number;
    name: string;
    email: string;
    role: 'teacher' | 'student';
    role_label: string;
};

type Props = {
    classroom: Classroom;
    members: Member[];
    canAssignTeachers: boolean;
};

export default function ClassroomMembers({
    classroom,
    members,
    canAssignTeachers,
}: Props) {
    const form = useForm({ email: '', role: 'student' });

    const teachers = members.filter((m) => m.role === 'teacher');
    const students = members.filter((m) => m.role === 'student');

    const remove = (member: Member) => {
        if (confirm(`Remover ${member.name} da classe ${classroom.name}?`)) {
            router.delete(
                destroy.url({ classroom: classroom.slug, user: member.id }),
                { preserveScroll: true },
            );
        }
    };

    const list = (title: string, items: Member[]) => (
        <section>
            <h2 className="mb-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                {title} ({items.length})
            </h2>
            {items.length === 0 ? (
                <p className="text-sm text-muted-foreground">Ninguém ainda.</p>
            ) : (
                <ul className="divide-y rounded-2xl border bg-card">
                    {items.map((member) => (
                        <li
                            key={member.id}
                            className="flex items-center gap-3 px-4 py-3"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-medium">
                                    {member.name}
                                </p>
                                <p className="truncate text-sm text-muted-foreground">
                                    {member.email}
                                </p>
                            </div>
                            {(member.role === 'student' ||
                                canAssignTeachers) && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => remove(member)}
                                    aria-label={`Remover ${member.name}`}
                                >
                                    <UserMinus />
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );

    return (
        <>
            <Head title={`Membros · ${classroom.name}`} />
            <Page>
                <PageHeader
                    eyebrow={`Classe ${classroom.name}`}
                    title="Membros"
                    description="Membros veem as lições marcadas como “somente membros”."
                />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.submit(store(classroom.slug), {
                            preserveScroll: true,
                            onSuccess: () => form.reset('email'),
                        });
                    }}
                    className="mb-8 space-y-3 rounded-2xl border border-dashed p-4"
                >
                    <p className="text-sm font-medium">Adicionar pessoa</p>
                    <div className="grid gap-3 sm:grid-cols-[1fr_12rem]">
                        <Field
                            label="E-mail da conta"
                            htmlFor="email"
                            error={form.errors.email}
                        >
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                                placeholder="pessoa@email.com"
                                required
                            />
                        </Field>
                        <Field
                            label="Papel"
                            htmlFor="role"
                            error={form.errors.role}
                        >
                            <NativeSelect
                                id="role"
                                value={form.data.role}
                                onChange={(e) =>
                                    form.setData('role', e.target.value)
                                }
                            >
                                <option value="student">Aluno(a)</option>
                                {canAssignTeachers && (
                                    <option value="teacher">
                                        Professor(a)
                                    </option>
                                )}
                            </NativeSelect>
                        </Field>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        A pessoa precisa ter criado uma conta. Se já for membro,
                        o papel é atualizado.
                    </p>
                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            variant="secondary"
                            disabled={form.processing}
                        >
                            <UserPlus /> Adicionar
                        </Button>
                    </div>
                </form>

                <div className="space-y-8">
                    {list('Professores', teachers)}
                    {list('Alunos', students)}
                </div>
            </Page>
        </>
    );
}
