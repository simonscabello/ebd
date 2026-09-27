import { useForm } from '@inertiajs/react';
import { CalendarPlus } from 'lucide-react';
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
import { NativeSelect } from '@/components/ui/native-select';
import { store } from '@/routes/admin/classrooms/meetings';
import type { Classroom, LessonOption } from '@/types';

export function AddMeetingDialog({
    classroom,
    lessons,
    nextSunday,
}: {
    classroom: Classroom;
    lessons: LessonOption[];
    nextSunday: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<{
        held_on: string;
        lesson_id: number | '';
        title: string;
    }>({
        held_on: nextSunday,
        lesson_id: '',
        title: '',
    });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <CalendarPlus /> Adicionar domingo
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((values) => ({
                            ...values,
                            lesson_id: values.lesson_id || null,
                            title: values.title || null,
                        }));
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
                        <DialogTitle>Adicionar domingo</DialogTitle>
                        <DialogDescription>
                            Um domingo (ou outro dia) de aula da classe.
                        </DialogDescription>
                    </DialogHeader>
                    <Field
                        label="Data"
                        htmlFor="meeting-date"
                        error={form.errors.held_on}
                    >
                        <Input
                            id="meeting-date"
                            type="date"
                            value={form.data.held_on}
                            onChange={(event) =>
                                form.setData('held_on', event.target.value)
                            }
                            required
                        />
                    </Field>
                    <Field
                        label="Lição"
                        htmlFor="meeting-lesson"
                        error={form.errors.lesson_id}
                    >
                        <NativeSelect
                            id="meeting-lesson"
                            value={form.data.lesson_id}
                            onChange={(event) =>
                                form.setData(
                                    'lesson_id',
                                    event.target.value
                                        ? Number(event.target.value)
                                        : '',
                                )
                            }
                        >
                            <option value="">A definir</option>
                            {lessons.map((lesson) => (
                                <option key={lesson.id} value={lesson.id}>
                                    {lesson.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    <Field
                        label="Título (opcional)"
                        htmlFor="meeting-title"
                        error={form.errors.title}
                        hint="Para domingos especiais, ex.: Revisão do trimestre."
                    >
                        <Input
                            id="meeting-title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            maxLength={120}
                        />
                    </Field>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Adicionar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
