import { useForm } from '@inertiajs/react';
import { Field } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { meetingWords } from '@/lib/meeting-words';
import { update } from '@/routes/admin/meetings';
import type { Classroom } from '@/types';

/**
 * Data e título do domingo (ex.: "Revisão do trimestre").
 */
export function EditMeetingDialog({
    meeting,
    open,
    onOpenChange,
    classroom,
}: {
    meeting: { id: number; held_on: string; title: string | null };
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Dia da classe (padrão: domingo). */
    classroom?: Pick<Classroom, 'meeting_weekday' | 'program_short_name'>;
}) {
    const words = meetingWords(classroom);
    const form = useForm({
        held_on: meeting.held_on,
        title: meeting.title ?? '',
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((values) => ({
                            ...values,
                            title: values.title || null,
                        }));
                        form.put(update.url(meeting.id), {
                            preserveScroll: true,
                            onSuccess: () => onOpenChange(false),
                        });
                    }}
                    className="space-y-4"
                >
                    <DialogHeader>
                        <DialogTitle>Editar {words.one}</DialogTitle>
                        <DialogDescription>
                            Para trocar a lição, use a seção Lição do dia.
                        </DialogDescription>
                    </DialogHeader>
                    <Field
                        label="Data"
                        htmlFor="edit-meeting-date"
                        error={form.errors.held_on}
                    >
                        <Input
                            id="edit-meeting-date"
                            type="date"
                            value={form.data.held_on}
                            onChange={(event) =>
                                form.setData('held_on', event.target.value)
                            }
                            required
                        />
                    </Field>
                    <Field
                        label="Título (opcional)"
                        htmlFor="edit-meeting-title"
                        error={form.errors.title}
                        hint={`Para ${words.many} especiais, ex.: Revisão da série.`}
                    >
                        <Input
                            id="edit-meeting-title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            maxLength={120}
                        />
                    </Field>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Voltar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Salvar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
