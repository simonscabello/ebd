import { ShieldAlert } from 'lucide-react';
import { CopyWhatsAppButtons } from '@/components/copy-whatsapp-button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type IssuedLink = {
    user_id: number;
    name: string;
    phone: string | null;
    url: string;
    message: string;
};

/**
 * Mostra o link pessoal recém-gerado. Ele não fica guardado em lugar nenhum:
 * depois de fechar, só gerando outro.
 */
export function AccessLinkDialog({
    link,
    onClose,
}: {
    link: IssuedLink | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={link !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                {link && (
                    <>
                        <DialogHeader>
                            <DialogTitle>
                                Link de acesso de {link.name}
                            </DialogTitle>
                            <DialogDescription>
                                Envie para a pessoa pelo WhatsApp. Ao tocar no
                                link, ela entra e o aparelho fica conectado.
                            </DialogDescription>
                        </DialogHeader>
                        <p className="rounded-xl bg-muted/60 p-3 font-mono text-xs break-all">
                            {link.url}
                        </p>
                        <CopyWhatsAppButtons
                            text={link.message}
                            phone={link.phone}
                            copyLabel="Copiar mensagem"
                        />
                        <p className="flex gap-2 text-xs text-muted-foreground">
                            <ShieldAlert className="size-4 shrink-0" />
                            Quem tiver este link entra como {link.name}. Por
                            segurança ele aparece só agora; se perder, gere um
                            novo (o anterior deixa de funcionar).
                        </p>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
