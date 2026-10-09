import { Paperclip, X } from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type Props = Omit<ComponentProps<'input'>, 'type' | 'onChange' | 'value'> & {
    id: string;
    file: File | null;
    onFileChange: (file: File | null) => void;
};

/**
 * Seletor de arquivo com a cara do app (o nativo muda de idioma e de visual
 * em cada navegador). O <input> continua lá, acessível pelo rótulo do campo.
 */
export function FileInput({
    id,
    file,
    onFileChange,
    className,
    ...props
}: Props) {
    const input = useRef<HTMLInputElement>(null);

    // Formulário limpo (depois de enviar ou ao trocar o tipo): esvazia o
    // <input> também, para o mesmo arquivo poder ser escolhido de novo.
    useEffect(() => {
        if (!file && input.current) input.current.value = '';
    }, [file]);

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <input
                {...props}
                ref={input}
                id={id}
                type="file"
                className="peer sr-only"
                onChange={(event) =>
                    onFileChange(event.target.files?.[0] ?? null)
                }
            />
            <label
                htmlFor={id}
                className="inline-flex h-10 shrink-0 cursor-pointer items-center gap-2 rounded-lg border bg-card px-3 text-sm font-medium shadow-xs transition-colors peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50 peer-aria-invalid:border-destructive hover:bg-accent"
            >
                <Paperclip className="size-4" />
                {file ? 'Trocar arquivo' : 'Escolher arquivo'}
            </label>
            <span className="min-w-0 flex-1 truncate text-sm text-muted-foreground">
                {file ? file.name : 'Nenhum arquivo escolhido'}
            </span>
            {file && (
                <button
                    type="button"
                    onClick={() => onFileChange(null)}
                    className="rounded-md p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                    aria-label="Remover arquivo escolhido"
                >
                    <X className="size-4" />
                </button>
            )}
        </div>
    );
}
