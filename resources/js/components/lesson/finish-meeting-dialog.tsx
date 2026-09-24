import { router } from '@inertiajs/react';
import { CheckCircle2, ChevronsRight, Flag } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { finish } from '@/routes/admin/meetings';

/**
 * "Encerrar aula": a lição terminou ou continua no próximo domingo?
 */
export function FinishMeetingDialog({
    meetingId,
    initialNotes,
    children,
}: {
    meetingId: number;
    initialNotes: string | null;
    /** Botão que abre o diálogo (padrão: "Encerrar aula" largo). */
    children?: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [notes, setNotes] = useState(initialNotes ?? '');
    const [processing, setProcessing] = useState(false);

    const submit = (continues: boolean) => {
        setProcessing(true);
        router.post(
            finish.url(meetingId),
            { continues, notes },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {children ?? (
                    <Button variant="outline" size="lg" className="w-full">
                        <Flag /> Encerrar aula
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Encerrar aula</DialogTitle>
                    <DialogDescription>
                        A conversa rendeu? Se a lição não terminou, ela continua
                        no próximo domingo e as seguintes são empurradas.
                    </DialogDescription>
                </DialogHeader>
                <label htmlFor="finish-notes" className="text-sm font-medium">
                    Onde paramos (só professores veem)
                </label>
                <Textarea
                    id="finish-notes"
                    value={notes}
                    onChange={(event) => setNotes(event.target.value)}
                    rows={3}
                    placeholder="Ex.: paramos no tópico II.2; retomar a pergunta 3."
                />
                <div className="grid gap-2 sm:grid-cols-2">
                    <Button
                        size="lg"
                        disabled={processing}
                        onClick={() => submit(false)}
                    >
                        <CheckCircle2 /> Lição concluída
                    </Button>
                    <Button
                        size="lg"
                        variant="outline"
                        disabled={processing}
                        onClick={() => submit(true)}
                    >
                        <ChevronsRight /> Continua no próximo
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
