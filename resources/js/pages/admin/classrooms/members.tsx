import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ChartLine,
    KeyRound,
    Link2,
    ShieldOff,
    UserMinus,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import type { IssuedLink } from '@/components/admin/access-link-dialog';
import { AccessLinkDialog } from '@/components/admin/access-link-dialog';
import { Field } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { insights } from '@/routes/admin/classrooms';
import { destroy, store } from '@/routes/admin/classrooms/members';
import {
    destroy as revokeLink,
    store as issueLink,
} from '@/routes/admin/classrooms/members/link';
import {
    show as studentPage,
    store as storeStudent,
} from '@/routes/admin/classrooms/students';
import type { Classroom } from '@/types';

type Member = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    role: 'teacher' | 'student';
    role_label: string;
    is_managed: boolean;
    access_link: {
        created_at: string;
        use_count: number;
        last_used_at: string | null;
        expires_at: string | null;
    } | null;
};

type Props = {
    classroom: Classroom;
    members: Member[];
    canAssignTeachers: boolean;
    isAdmin: boolean;
};

const relative = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' });

function ago(iso: string): string {
    const days = Math.round(
        (new Date(iso).getTime() - Date.now()) / 86_400_000,
    );

    return relative.format(days, 'day');
}

export default function ClassroomMembers({
    classroom,
    members,
    canAssignTeachers,
    isAdmin,
}: Props) {
    const [issued, setIssued] = useState<IssuedLink | null>(null);
    const byEmail = useForm({ email: '', role: 'student' });
    const managed = useForm({ name: '', phone: '' });

    const teachers = members.filter((m) => m.role === 'teacher');
    const students = members.filter((m) => m.role === 'student');

    const onFlash = (flash: Record<string, unknown>) => {
        if (flash.accessLink) {
            setIssued(flash.accessLink as IssuedLink);
        }
    };

    const remove = (member: Member) => {
        if (confirm(`Remover ${member.name} da classe ${classroom.name}?`)) {
            router.delete(
                destroy.url({ classroom: classroom.slug, user: member.id }),
                { preserveScroll: true },
            );
        }
    };

    const generate = (member: Member) => {
        if (
            member.access_link &&
            !confirm(
                `Gerar um novo link para ${member.name}? O link atual deixa de funcionar.`,
            )
        ) {
            return;
        }

        router.post(
            issueLink.url({ classroom: classroom.slug, user: member.id }),
            {},
            { preserveScroll: true, onFlash },
        );
    };

    const block = (member: Member) => {
        if (
            confirm(
                `Bloquear o acesso de ${member.name}? Todos os aparelhos conectados saem da conta.`,
            )
        ) {
            router.delete(
                revokeLink.url({ classroom: classroom.slug, user: member.id }),
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
                            className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-medium">
                                    {member.role === 'student' ? (
                                        <Link
                                            href={studentPage({
                                                classroom: classroom.slug,
                                                user: member.id,
                                            })}
                                            className="hover:underline"
                                        >
                                            {member.name}
                                        </Link>
                                    ) : (
                                        member.name
                                    )}
                                </p>
                                <p className="truncate text-sm text-muted-foreground">
                                    {member.email ??
                                        (member.is_managed
                                            ? 'Entra pelo link pessoal'
                                            : '')}
                                </p>
                                {member.role === 'student' &&
                                    member.access_link && (
                                        <p className="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                                            <KeyRound className="size-3.5" />
                                            Link ativo · usado{' '}
                                            {member.access_link.use_count}x
                                            {member.access_link.last_used_at &&
                                                ` · último acesso ${ago(member.access_link.last_used_at)}`}
                                        </p>
                                    )}
                            </div>
                            <div className="flex flex-wrap gap-1">
                                {member.role === 'student' &&
                                    (member.is_managed || isAdmin) && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => generate(member)}
                                        >
                                            <Link2 />
                                            {member.access_link
                                                ? 'Novo link'
                                                : 'Gerar link'}
                                        </Button>
                                    )}
                                {member.role === 'student' &&
                                    member.access_link && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => block(member)}
                                        >
                                            <ShieldOff /> Bloquear
                                        </Button>
                                    )}
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
                            </div>
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
                    description="Alunos entram pelo link pessoal (sem senha) e passam a registrar leitura, revisão e presença."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={insights(classroom.slug)}>
                                <ChartLine /> Evolução
                            </Link>
                        </Button>
                    }
                />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        managed.submit(storeStudent(classroom.slug), {
                            preserveScroll: true,
                            onFlash,
                            onSuccess: () => managed.reset(),
                        });
                    }}
                    className="mb-4 space-y-3 rounded-2xl border border-dashed p-4"
                >
                    <p className="text-sm font-medium">Adicionar aluno</p>
                    <div className="grid gap-3 sm:grid-cols-[1fr_12rem]">
                        <Field
                            label="Nome"
                            htmlFor="student-name"
                            error={managed.errors.name}
                        >
                            <Input
                                id="student-name"
                                value={managed.data.name}
                                onChange={(e) =>
                                    managed.setData('name', e.target.value)
                                }
                                placeholder="Nome completo"
                                required
                            />
                        </Field>
                        <Field
                            label="WhatsApp (opcional)"
                            htmlFor="student-phone"
                            error={managed.errors.phone}
                        >
                            <Input
                                id="student-phone"
                                type="tel"
                                inputMode="tel"
                                value={managed.data.phone}
                                onChange={(e) =>
                                    managed.setData('phone', e.target.value)
                                }
                                placeholder="55 11 99999-8888"
                            />
                        </Field>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Não precisa de e-mail nem senha: geramos um link pessoal
                        para você enviar no WhatsApp.
                    </p>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={managed.processing}>
                            <UserPlus /> Adicionar e gerar link
                        </Button>
                    </div>
                </form>

                <details className="mb-8 rounded-2xl border border-dashed p-4">
                    <summary className="cursor-pointer text-sm font-medium">
                        Adicionar pessoa que já tem conta (por e-mail)
                    </summary>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            byEmail.submit(store(classroom.slug), {
                                preserveScroll: true,
                                onSuccess: () => byEmail.reset('email'),
                            });
                        }}
                        className="mt-3 space-y-3"
                    >
                        <div className="grid gap-3 sm:grid-cols-[1fr_12rem]">
                            <Field
                                label="E-mail da conta"
                                htmlFor="email"
                                error={byEmail.errors.email}
                            >
                                <Input
                                    id="email"
                                    type="email"
                                    value={byEmail.data.email}
                                    onChange={(e) =>
                                        byEmail.setData('email', e.target.value)
                                    }
                                    placeholder="pessoa@email.com"
                                    required
                                />
                            </Field>
                            <Field
                                label="Papel"
                                htmlFor="role"
                                error={byEmail.errors.role}
                            >
                                <NativeSelect
                                    id="role"
                                    value={byEmail.data.role}
                                    onChange={(e) =>
                                        byEmail.setData('role', e.target.value)
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
                            A pessoa precisa ter criado uma conta. Se já for
                            membro, o papel é atualizado.
                        </p>
                        <div className="flex justify-end">
                            <Button
                                type="submit"
                                variant="secondary"
                                disabled={byEmail.processing}
                            >
                                <UserPlus /> Adicionar
                            </Button>
                        </div>
                    </form>
                </details>

                <div className="space-y-8">
                    {list('Professores', teachers)}
                    {list('Alunos', students)}
                </div>
            </Page>

            <AccessLinkDialog link={issued} onClose={() => setIssued(null)} />
        </>
    );
}
