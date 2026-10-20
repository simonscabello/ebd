import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/** Um texto para copiar (chave Pix, Pix copia e cola) com o botão ao lado. */
export function CopyField({
    label,
    value,
    copyLabel = 'Copiar',
    className,
}: {
    label: string;
    value: string;
    copyLabel?: string;
    className?: string;
}) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 2500);
        } catch {
            window.prompt('Copie o texto:', value);
        }
    };

    return (
        <div className={cn('space-y-1.5', className)}>
            <p className="text-sm font-medium">{label}</p>
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <code className="min-w-0 flex-1 rounded-lg border border-border bg-muted px-3 py-2.5 font-mono text-[13px] break-all">
                    {value}
                </code>
                <Button
                    type="button"
                    variant="outline"
                    onClick={copy}
                    className="shrink-0"
                    aria-live="polite"
                >
                    {copied ? <Check /> : <Copy />}
                    {copied ? 'Copiado' : copyLabel}
                </Button>
            </div>
        </div>
    );
}
