import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Contêiner de página. "reading" mantém a largura ideal para leitura.
 */
export function Page({
    children,
    width = 'reading',
    className,
}: {
    children: ReactNode;
    width?: 'reading' | 'wide';
    className?: string;
}) {
    return (
        <div
            className={cn(
                'mx-auto w-full px-4 pt-6 sm:px-6 md:pt-10',
                width === 'reading' ? 'max-w-2xl' : 'max-w-4xl',
                className,
            )}
        >
            {children}
        </div>
    );
}

export function PageHeader({
    title,
    description,
    eyebrow,
    actions,
}: {
    title: string;
    description?: ReactNode;
    eyebrow?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="space-y-1.5">
                {eyebrow && (
                    <p className="text-sm font-medium text-primary">
                        {eyebrow}
                    </p>
                )}
                <h1 className="font-serif text-3xl font-semibold tracking-tight text-balance">
                    {title}
                </h1>
                {description && (
                    <p className="text-muted-foreground">{description}</p>
                )}
            </div>
            {actions && <div className="flex shrink-0 gap-2">{actions}</div>}
        </header>
    );
}

export function Section({
    id,
    title,
    icon,
    description,
    children,
    className,
}: {
    id?: string;
    title: string;
    icon?: ReactNode;
    description?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section id={id} className={cn('scroll-mt-36', className)}>
            <div className="mb-4">
                <h2 className="flex items-center gap-2 text-lg font-semibold tracking-tight">
                    {icon && (
                        <span className="text-primary [&_svg]:size-5">
                            {icon}
                        </span>
                    )}
                    {title}
                </h2>
                {description && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}

export function EmptyState({
    icon,
    title,
    children,
}: {
    icon?: ReactNode;
    title: string;
    children?: ReactNode;
}) {
    return (
        <div className="rounded-2xl border border-dashed bg-card/60 px-6 py-10 text-center">
            {icon && (
                <div className="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground [&_svg]:size-6">
                    {icon}
                </div>
            )}
            <p className="font-medium">{title}</p>
            {children && (
                <div className="mt-2 text-sm text-muted-foreground">
                    {children}
                </div>
            )}
        </div>
    );
}
