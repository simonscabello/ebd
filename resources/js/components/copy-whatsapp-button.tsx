import { Check, Copy, MessageCircle } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

/**
 * Copia um texto pronto (ou abre o WhatsApp com ele). Útil para a mensagem
 * da semana e para os links de acesso dos alunos.
 */
export function CopyWhatsAppButtons({
    text,
    phone,
    copyLabel = 'Copiar',
}: {
    text: string;
    phone?: string | null;
    copyLabel?: string;
}) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            setTimeout(() => setCopied(false), 2500);
        } catch {
            window.prompt('Copie o texto:', text);
        }
    };

    const digits = phone?.replace(/\D/g, '') ?? '';
    const whatsapp = `https://wa.me/${digits}?text=${encodeURIComponent(text)}`;

    return (
        <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" onClick={copy}>
                {copied ? <Check /> : <Copy />}
                {copied ? 'Copiado' : copyLabel}
            </Button>
            <Button asChild variant="outline">
                <a href={whatsapp} target="_blank" rel="noopener noreferrer">
                    <MessageCircle /> WhatsApp
                </a>
            </Button>
        </div>
    );
}
