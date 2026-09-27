import { useForm } from '@inertiajs/react';
import { AlertTriangle, UserPlus } from 'lucide-react';
import { useState } from 'react';
import type { IssuedLink } from '@/components/admin/access-link-dialog';
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
import { store } from '@/routes/admin/classrooms/students';
import type { Classroom } from '@/types';

/**
 * Cadastra o aluno só com nome e WhatsApp e já gera o link pessoal. Se já
 * existe alguém com nome parecido na classe, pede confirmação antes.
 */
export function AddStudentDialog({
    classroom,
    onIssued,
}: {
    classroom: Classroom;
    onIssued: (link: IssuedLink) => void;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', phone: '', confirm_duplicate: false });
    const duplicate = (form.errors as Record<string, string | undefined>)
        .duplicate;

    const submit = (confirmDuplicate: boolean) => {
        form.transform((data) => ({
            ...data,
            confirm_duplicate: confirmDuplicate,
        }));
        form.submit(store(classroom.slug), {
            preserveScroll: true,
            onFlash: (flash) => {
                if (flash.accessLink) {
                    onIssued(flash.accessLink as IssuedLink);
                }
            },
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);
                if (!value) {
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button>
                    <UserPlus /> Adicionar aluno
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit(false);
                    }}
                    className="space-y-4"
                >
                    <DialogHeader>
                        <DialogTitle>Adicionar aluno</DialogTitle>
                        <DialogDescription>
                            Só o nome e o WhatsApp. No primeiro acesso pelo
                            link, a pessoa completa o cadastro.
                        </DialogDescription>
                    </DialogHeader>
                    <Field
                        label="Nome"
                        htmlFor="student-name"
                        error={form.errors.name}
                    >
                        <Input
                            id="student-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            autoComplete="off"
                            required
                            autoFocus
                        />
                    </Field>
                    <Field
                        label="WhatsApp (opcional)"
                        htmlFor="student-phone"
                        error={form.errors.phone}
                        hint="Com DDD. Usado para enviar o link."
                    >
                        <Input
                            id="student-phone"
                            type="tel"
                            inputMode="tel"
                            value={form.data.phone}
                            onChange={(event) =>
                                form.setData('phone', event.target.value)
                            }
                            placeholder="(27) 99999-0000"
                        />
                    </Field>
                    {duplicate && (
                        <div className="flex gap-2 rounded-xl border border-warning/50 bg-warning-soft p-3 text-sm text-warning-foreground">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                            <div className="space-y-2">
                                <p>{duplicate}</p>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => submit(true)}
                                    disabled={form.processing}
                                >
                                    É outra pessoa, adicionar
                                </Button>
                            </div>
                        </div>
                    )}
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Adicionar e gerar link
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
