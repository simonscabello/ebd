import { useForm } from '@inertiajs/react';
import { AtSign } from 'lucide-react';
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
import { store } from '@/routes/admin/classrooms/members';
import type { Classroom } from '@/types';

/**
 * Traz para a classe alguém que já tem conta no app (pelo e-mail).
 */
export function AddByEmailDialog({ classroom }: { classroom: Classroom }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ email: '', role: 'student' });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <AtSign /> Já tem conta
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.submit(store(classroom.slug), {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset();
                                setOpen(false);
                            },
                        });
                    }}
                    className="space-y-4"
                >
                    <DialogHeader>
                        <DialogTitle>Adicionar quem já tem conta</DialogTitle>
                        <DialogDescription>
                            Para quem criou a conta sozinho no app. Informe o
                            e-mail que a pessoa usa para entrar.
                        </DialogDescription>
                    </DialogHeader>
                    <Field
                        label="E-mail da conta"
                        htmlFor="member-email"
                        error={form.errors.email}
                    >
                        <Input
                            id="member-email"
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            required
                            autoFocus
                        />
                    </Field>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Adicionar à classe
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
