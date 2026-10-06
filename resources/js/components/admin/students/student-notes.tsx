import { router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/admin/classrooms/students/notes';
import { destroy, update } from '@/routes/admin/notes';
import type { Classroom } from '@/types';

export type StudentNote = {
    id: number;
    body: string;
    author: string | null;
    created_at: string;
    can_edit: boolean;
};

const dateTime = new Intl.DateTimeFormat('pt-BR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

/**
 * Anotações do professor sobre o aluno (ex.: "mudou de turno", "visitar").
 * Só professores da classe e a administração veem; o aluno nunca.
 */
export function StudentNotes({
    classroom,
    studentId,
    notes,
}: {
    classroom: Classroom;
    studentId: number;
    notes: StudentNote[];
}) {
    const form = useForm({ body: '' });

    return (
        <div className="space-y-4">
            <form
                noValidate
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(
                        store.url({
                            classroom: classroom.slug,
                            user: studentId,
                        }),
                        {
                            preserveScroll: true,
                            onSuccess: () => form.reset(),
                        },
                    );
                }}
                className="space-y-2"
            >
                <Textarea
                    value={form.data.body}
                    onChange={(event) =>
                        form.setData('body', event.target.value)
                    }
                    rows={2}
                    placeholder="Ex.: está trabalhando aos domingos de manhã; combinar visita."
                    aria-label="Nova anotação"
                    maxLength={5000}
                />
                {form.errors.body && (
                    <p className="text-sm text-destructive">
                        {form.errors.body}
                    </p>
                )}
                <div className="flex justify-end">
                    <Button
                        type="submit"
                        disabled={
                            form.processing || form.data.body.trim() === ''
                        }
                    >
                        Salvar anotação
                    </Button>
                </div>
            </form>

            {notes.length > 0 && (
                <ul className="divide-y rounded-2xl border bg-card">
                    {notes.map((note) => (
                        <NoteItem key={note.id} note={note} />
                    ))}
                </ul>
            )}
        </div>
    );
}

function NoteItem({ note }: { note: StudentNote }) {
    const confirm = useConfirm();
    const [editing, setEditing] = useState(false);
    const form = useForm({ body: note.body });

    const remove = async () => {
        if (
            await confirm({
                title: 'Apagar esta anotação?',
                confirmLabel: 'Apagar',
                destructive: true,
            })
        ) {
            router.delete(destroy.url(note.id), { preserveScroll: true });
        }
    };

    return (
        <li className="px-4 py-3">
            <p className="flex items-center gap-2 text-xs text-muted-foreground">
                <span>{dateTime.format(new Date(note.created_at))}</span>
                {note.author && <span>· {note.author}</span>}
                {note.can_edit && !editing && (
                    <span className="ml-auto flex gap-1">
                        <Button
                            variant="outline"
                            size="icon"
                            className="size-8"
                            onClick={() => setEditing(true)}
                            aria-label="Editar anotação"
                        >
                            <Pencil />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            className="size-8 border-destructive/30 text-destructive hover:bg-destructive/10 hover:text-destructive"
                            onClick={remove}
                            aria-label="Apagar anotação"
                        >
                            <Trash2 />
                        </Button>
                    </span>
                )}
            </p>
            {editing ? (
                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(update.url(note.id), {
                            preserveScroll: true,
                            onSuccess: () => setEditing(false),
                        });
                    }}
                    className="mt-2 space-y-2"
                >
                    <Textarea
                        value={form.data.body}
                        onChange={(event) =>
                            form.setData('body', event.target.value)
                        }
                        rows={3}
                        aria-label="Anotação"
                        maxLength={5000}
                    />
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                form.reset();
                                setEditing(false);
                            }}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing}
                        >
                            Salvar
                        </Button>
                    </div>
                </form>
            ) : (
                <p className="mt-1 text-sm whitespace-pre-line">{note.body}</p>
            )}
        </li>
    );
}
