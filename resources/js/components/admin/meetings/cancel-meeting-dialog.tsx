import { useForm } from '@inertiajs/react';
import { CalendarOff } from 'lucide-react';
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
import { cancel } from '@/routes/admin/meetings';
import type { Classroom } from '@/types';

/**
 * "Sem EBD": motivo (opcional). A lição atual segue para o próximo domingo.
 */
export function CancelMeetingDialog({
    meeting,
    dateLabel,
    open,
    onOpenChange,
    classroom,
}: {
    meeting: { id: number; lesson_id: number | null };
    dateLabel: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Dia e programa da classe (padrão: domingo, EBD). */
    classroom?: Pick<Classroom, 'meeting_weekday' | 'program_short_name'>;
}) {
    const words = meetingWords(classroom);
    const form = useForm({ reason: '' });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(cancel.url(meeting.id), {
                            preserveScroll: true,
                            onSuccess: () => onOpenChange(false),
                        });
                    }}
                    className="space-y-4"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {words.One} sem {words.program}
                        </DialogTitle>
                        <DialogDescription className="first-letter:uppercase">
                            {dateLabel}. O aviso aparece para a classe no
                            Início, e a lição atual fica para o próximo{' '}
                            {words.one}.
                        </DialogDescription>
                    </DialogHeader>
                    <Field
                        label="Motivo (opcional)"
                        htmlFor={`cancel-reason-${meeting.id}`}
                        error={form.errors.reason}
                    >
                        <Input
                            id={`cancel-reason-${meeting.id}`}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            placeholder="Ex.: Culto de Missões"
                            maxLength={120}
                            autoFocus
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
                            <CalendarOff /> Marcar sem {words.program}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
