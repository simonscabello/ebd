import { Check, Share2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

/**
 * Compartilhar a lição. No celular usa o menu nativo (que inclui o WhatsApp);
 * no computador abre o WhatsApp Web ou copia o link.
 */
export function ShareButton({
    url,
    title,
    text,
    variant = 'outline',
    size = 'default',
    label = 'Compartilhar',
}: {
    /** Sem url, o link vai só dentro do texto (evita link repetido no WhatsApp). */
    url?: string;
    title: string;
    text: string;
    variant?: 'outline' | 'default' | 'secondary' | 'ghost';
    size?: 'default' | 'sm';
    label?: string;
}) {
    const [copied, setCopied] = useState(false);

    const share = async () => {
        if (typeof navigator !== 'undefined' && navigator.share) {
            try {
                await navigator.share(
                    url ? { title, text, url } : { title, text },
                );

                return;
            } catch {
                // Usuário cancelou: não faz nada.
                return;
            }
        }

        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            setTimeout(() => setCopied(false), 2500);
        } catch {
            window.open(
                `https://wa.me/?text=${encodeURIComponent(text)}`,
                '_blank',
                'noopener',
            );
        }
    };

    return (
        <Button type="button" variant={variant} size={size} onClick={share}>
            {copied ? <Check /> : <Share2 />}
            {copied ? 'Link copiado' : label}
        </Button>
    );
}
