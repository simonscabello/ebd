import { useForm } from '@inertiajs/react';
import { CalendarOff } from 'lucide-react';
import { Field } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cancel } from '@/routes/admin/meetings';

/**
 * "Sem EBD": motivo (opcional) e, se houver lição marcada, a opção de
 * empurrar a lição e as seguintes para o próximo domingo.
 */
export function CancelMeetingDialog({
    meeting,
    dateLabel,
    open,
    onOpenChange,
}: {
    meeting: { id: number; lesson_id: number | null };
    dateLabel: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({ reason: '', shift: meeting.lesson_id !== null });

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
                        <DialogTitle>Domingo sem EBD</DialogTitle>
                        <DialogDescription className="first-letter:uppercase">
                            {dateLabel}. O aviso aparece para a classe no
                            Início.
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
                    {meeting.lesson_id !== null && (
                        <label className="flex cursor-pointer items-start gap-3 rounded-xl border bg-card p-3.5 text-sm">
                            <Checkbox
                                checked={form.data.shift}
                                onCheckedChange={(value) =>
                                    form.setData('shift', value === true)
                                }
                                className="mt-0.5"
                            />
                            <span>
                                Empurrar esta lição (e as seguintes) para o
                                próximo domingo
                                <span className="block text-muted-foreground">
                                    Desmarque se a lição simplesmente não será
                                    dada.
                                </span>
                            </span>
                        </label>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => onOpenChange(false)}
                        >
                            Voltar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            <CalendarOff /> Marcar sem EBD
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
