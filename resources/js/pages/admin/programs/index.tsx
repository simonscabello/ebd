import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ChevronRight,
    FolderPlus,
    Pencil,
    Save,
    UserMinus,
    UserPlus,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import { Field } from '@/components/form-field';
import { Page, PageHeader, Section } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes/admin';
import { index as classroomsIndex, show } from '@/routes/admin/classrooms';
import { store, update } from '@/routes/admin/programs';
import {
    destroy as removeCoordinator,
    store as addCoordinator,
} from '@/routes/admin/programs/coordinators';

type Person = { id: number; name: string; email: string | null };

type Program = {
    id: number;
    name: string;
    short_name: string;
    classrooms: {
        id: number;
        name: string;
        slug: string;
        is_active: boolean;
        meeting_weekday_label: string;
    }[];
    coordinators: Person[];
};

/**
 * Programas da igreja (EBD, Discipulado...): as classes de cada um e quem
 * coordena. Só a administração da igreja vê.
 */
export default function ProgramsIndex({ programs }: { programs: Program[] }) {
    return (
        <>
            <Head title="Programas" />
            <Page>
                <PageHeader
                    breadcrumbs={[
                        { title: 'Painel', href: dashboard.url() },
                        { title: 'Classes', href: classroomsIndex.url() },
                        { title: 'Programas' },
                    ]}
                    title="Programas"
                    description="Cada classe pertence a um programa. Quem coordena um programa cuida de todas as classes dele."
                />
                <div className="space-y-6">
                    {programs.map((program) => (
                        <ProgramCard key={program.id} program={program} />
                    ))}
                </div>
                <NewProgram />
            </Page>
        </>
    );
}

function ProgramForm({
    program,
    onDone,
    idPrefix,
}: {
    program?: Program;
    onDone?: () => void;
    idPrefix: string;
}) {
    const form = useForm({
        name: program?.name ?? '',
        short_name: program?.short_name ?? '',
    });

    return (
        <form
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                form.submit(program ? update(program.id) : store(), {
                    preserveScroll: true,
                    onSuccess: () => {
                        if (!program) {
                            form.reset();
                        }

                        onDone?.();
                    },
                });
            }}
            className="flex flex-col gap-2 sm:flex-row sm:items-start"
        >
            <div className="flex-1">
                <Field
                    label="Nome"
                    htmlFor={`${idPrefix}-name`}
                    error={form.errors.name}
                >
                    <Input
                        id={`${idPrefix}-name`}
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Discipulado"
                        maxLength={100}
                        required
                    />
                </Field>
            </div>
            <div className="sm:w-40">
                <Field
                    label="Nome curto"
                    htmlFor={`${idPrefix}-short`}
                    error={form.errors.short_name}
                    hint="Nos avisos: “Amanhã tem EBD!”"
                >
                    <Input
                        id={`${idPrefix}-short`}
                        value={form.data.short_name}
                        onChange={(e) =>
                            form.setData('short_name', e.target.value)
                        }
                        placeholder="Disc."
                        maxLength={30}
                        required
                    />
                </Field>
            </div>
            <div className="flex gap-2 sm:mt-7">
                <Button type="submit" disabled={form.processing}>
                    {program ? <Save /> : <FolderPlus />}
                    {program ? 'Salvar' : 'Criar programa'}
                </Button>
                {onDone && (
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={onDone}
                        aria-label="Cancelar"
                    >
                        <X />
                    </Button>
                )}
            </div>
        </form>
    );
}

function ProgramCard({ program }: { program: Program }) {
    const [editing, setEditing] = useState(false);
    const confirm = useConfirm();
    const form = useForm({ email: '' });

    const remove = async (person: Person) => {
        if (
            await confirm({
                title: `Tirar ${person.name} da coordenação?`,
                description: `${person.name} deixa de cuidar das classes de ${program.name}.`,
                confirmLabel: 'Tirar',
                destructive: true,
            })
        ) {
            router.delete(
                removeCoordinator.url({ program: program.id, user: person.id }),
                { preserveScroll: true },
            );
        }
    };

    return (
        <section className="rounded-2xl border bg-card p-4">
            {editing ? (
                <ProgramForm
                    program={program}
                    idPrefix={`program-${program.id}`}
                    onDone={() => setEditing(false)}
                />
            ) : (
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <h2 className="font-serif text-lg font-semibold">
                            {program.name}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Nome curto: {program.short_name}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setEditing(true)}
                    >
                        <Pencil /> Renomear
                    </Button>
                </div>
            )}

            <h3 className="mt-5 text-sm font-medium">Classes</h3>
            {program.classrooms.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Nenhuma classe ainda. Escolha este programa ao criar uma
                    classe.
                </p>
            ) : (
                <ul className="mt-2 divide-y rounded-xl border">
                    {program.classrooms.map((classroom) => (
                        <li key={classroom.id}>
                            <Link
                                href={show(classroom.slug)}
                                className="flex items-center gap-3 px-3 py-2.5 hover:bg-muted/50"
                            >
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-medium">
                                        {classroom.name}
                                    </span>
                                    <span className="block text-sm text-muted-foreground">
                                        {classroom.meeting_weekday_label}
                                        {!classroom.is_active && ' · Inativa'}
                                    </span>
                                </span>
                                <ChevronRight className="size-4 text-muted-foreground" />
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            <h3 className="mt-5 text-sm font-medium">Coordenação</h3>
            {program.coordinators.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Ninguém coordena este programa.
                </p>
            ) : (
                <ul className="mt-2 divide-y rounded-xl border">
                    {program.coordinators.map((person) => (
                        <li
                            key={person.id}
                            className="flex items-center gap-3 px-3 py-2.5"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-medium">
                                    {person.name}
                                </p>
                                <p className="truncate text-sm text-muted-foreground">
                                    {person.email}
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={() => remove(person)}
                                aria-label={`Tirar ${person.name} da coordenação`}
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
                    form.post(addCoordinator.url(program.id), {
                        preserveScroll: true,
                        onSuccess: () => form.reset('email'),
                    });
                }}
                className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start"
            >
                <div className="flex-1">
                    <Field
                        label="E-mail de quem vai coordenar"
                        htmlFor={`coordinator-${program.id}`}
                        error={form.errors.email}
                    >
                        <Input
                            id={`coordinator-${program.id}`}
                            type="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            placeholder="pessoa@email.com"
                            required
                        />
                    </Field>
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                    className="sm:mt-7"
                >
                    <UserPlus /> Nomear
                </Button>
            </form>
        </section>
    );
}

function NewProgram() {
    return (
        <Section
            title="Novo programa"
            icon={<FolderPlus />}
            className="mt-12"
            description="Ex.: Discipulado, Pequenos grupos. As classes escolhem o programa no cadastro."
        >
            <ProgramForm idPrefix="new-program" />
        </Section>
    );
}
