import { cloneElement, isValidElement } from 'react';
import type { ReactElement, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type AriaProps = {
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
};

/**
 * Rótulo + campo + dica/erro. Quando o campo é um único elemento, ele recebe
 * `aria-invalid` e `aria-describedby` para o leitor de tela anunciar o erro.
 */
export function Field({
    label,
    htmlFor,
    error,
    hint,
    children,
    className,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    hint?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    const hintId = htmlFor && hint ? `${htmlFor}-hint` : undefined;
    const errorId = htmlFor && error ? `${htmlFor}-error` : undefined;

    const control =
        htmlFor && isValidElement<AriaProps>(children)
            ? cloneElement(children as ReactElement<AriaProps>, {
                  'aria-invalid': error ? true : undefined,
                  'aria-describedby':
                      [errorId, hintId].filter(Boolean).join(' ') || undefined,
              })
            : children;

    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={htmlFor}>{label}</Label>
            {control}
            {hint && !error && (
                <p id={hintId} className="text-xs text-muted-foreground">
                    {hint}
                </p>
            )}
            <InputError id={errorId} message={error} />
        </div>
    );
}
