import { Form } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import type { GenderOption } from '@/components/gender-field';
import { GenderField } from '@/components/gender-field';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import { todayOnDevice } from '@/lib/dates';
import { formatPhone } from '@/lib/phone';
import { update } from '@/routes/admin/classrooms/students';
import type { Classroom } from '@/types';

export type EditableStudent = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    birth_date: string | null;
    gender: string | null;
};

/**
 * Corrige os dados do aluno. O e-mail (login) só aparece quando o professor
 * pode mexer nele: conta ainda sem senha, ou quem edita é da administração.
 */
export function EditStudentDialog({
    classroom,
    student,
    genders,
    canEditEmail,
}: {
    classroom: Classroom;
    student: EditableStudent;
    genders: GenderOption[];
    canEditEmail: boolean;
}) {
    const [open, setOpen] = useState(false);
    const today = todayOnDevice();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Pencil /> Editar
                </Button>
            </DialogTrigger>
            <DialogContent>
                <Form
                    noValidate
                    {...update.form({
                        classroom: classroom.slug,
                        user: student.id,
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Dados de {student.name}
                                </DialogTitle>
                                <DialogDescription>
                                    Corrija o que estiver errado. O aluno também
                                    pode atualizar em “Meus dados”.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="grid gap-2">
                                <Label htmlFor="edit-name">Nome</Label>
                                <Input
                                    id="edit-name"
                                    name="name"
                                    defaultValue={student.name}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="edit-phone">WhatsApp</Label>
                                <Input
                                    id="edit-phone"
                                    name="phone"
                                    type="tel"
                                    inputMode="tel"
                                    defaultValue={formatPhone(student.phone)}
                                />
                                <InputError message={errors.phone} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="edit-birth">
                                    Data de nascimento
                                </Label>
                                <Input
                                    id="edit-birth"
                                    name="birth_date"
                                    type="date"
                                    min="1900-01-01"
                                    max={today}
                                    defaultValue={student.birth_date ?? ''}
                                />
                                <InputError message={errors.birth_date} />
                            </div>
                            <GenderField
                                options={genders}
                                defaultValue={student.gender}
                                error={errors.gender}
                            />
                            {canEditEmail && (
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-email">E-mail</Label>
                                    <Input
                                        id="edit-email"
                                        name="email"
                                        type="email"
                                        defaultValue={student.email ?? ''}
                                    />
                                    <InputError message={errors.email} />
                                </div>
                            )}
                            <InputError
                                message={
                                    (errors as Record<string, string>).student
                                }
                            />
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    Salvar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
