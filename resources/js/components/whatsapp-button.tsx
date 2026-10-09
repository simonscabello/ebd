import { MessageCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { whatsappUrl } from '@/lib/whatsapp';

/**
 * Abre o WhatsApp da pessoa com uma mensagem pronta (editável lá).
 */
export function WhatsAppButton({
    phone,
    text,
    label = 'WhatsApp',
    size = 'sm',
    variant = 'outline',
}: {
    phone: string | null | undefined;
    text: string;
    label?: string;
    size?: 'sm' | 'default';
    variant?: 'outline' | 'ghost' | 'default';
}) {
    if (!phone) {
        return null;
    }

    return (
        <Button asChild size={size} variant={variant}>
            <a
                href={whatsappUrl(phone, text)}
                target="_blank"
                rel="noopener noreferrer"
            >
                <MessageCircle /> {label}
            </a>
        </Button>
    );
}
