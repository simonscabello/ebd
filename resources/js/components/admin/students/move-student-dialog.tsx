import { useForm } from '@inertiajs/react';
import { ArrowRightLeft } from 'lucide-react';
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
import { NativeSelect } from '@/components/ui/native-select';
import { move } from '@/routes/admin/classrooms/students';
import type { Classroom } from '@/types';

/**
 * Passa o aluno para outra classe (ex.: mudou de faixa etária). O histórico
 * fica na classe de origem.
 */
export function MoveStudentDialog({
    classroom,
    student,
    targets,
}: {
    classroom: Classroom;
    student: { id: number; name: string };
    targets: { slug: string; name: string }[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ to: targets[0]?.slug ?? '' });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <ArrowRightLeft /> Mover de classe
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(
                            move.url({
                                classroom: classroom.slug,
                                user: student.id,
                            }),
                        );
                    }}
                    className="space-y-4"
                >
                    <DialogHeader>
                        <DialogTitle>Mover {student.name}</DialogTitle>
                        <DialogDescription>
                            As presenças e leituras até hoje ficam no histórico
                            da classe {classroom.name}. O link de acesso
                            continua valendo.
                        </DialogDescription>
                    </DialogHeader>
                    <Field
                        label="Nova classe"
                        htmlFor="move-to"
                        error={form.errors.to}
                    >
                        <NativeSelect
                            id="move-to"
                            value={form.data.to}
                            onChange={(event) =>
                                form.setData('to', event.target.value)
                            }
                        >
                            {targets.map((target) => (
                                <option key={target.slug} value={target.slug}>
                                    {target.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Mover
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
