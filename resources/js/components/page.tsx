import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type Crumb = { title: string; href?: string };

/**
 * Caminho da página (Flowbite breadcrumb): de onde viemos e como voltar.
 */
export function Breadcrumbs({
    items,
    className,
}: {
    items: Crumb[];
    className?: string;
}) {
    return (
        <nav aria-label="Caminho" className={cn('mb-4', className)}>
            <ol className="flex flex-wrap items-center gap-x-1 gap-y-1 text-sm">
                {items.map((item, index) => {
                    const last = index === items.length - 1;

                    return (
                        <li
                            key={`${item.title}-${index}`}
                            className="inline-flex items-center gap-1"
                        >
                            {index > 0 && (
                                <ChevronRight
                                    className="size-3.5 text-muted-foreground/70"
                                    aria-hidden
                                />
                            )}
                            {item.href && !last ? (
                                <Link
                                    href={item.href}
                                    className="rounded-md px-0.5 text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    {item.title}
                                </Link>
                            ) : (
                                <span
                                    className={cn(
                                        'px-0.5',
                                        last
                                            ? 'font-medium text-foreground'
                                            : 'text-muted-foreground',
                                    )}
                                    aria-current={last ? 'page' : undefined}
                                >
                                    {item.title}
                                </span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

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
    breadcrumbs,
}: {
    title: string;
    description?: ReactNode;
    eyebrow?: ReactNode;
    actions?: ReactNode;
    breadcrumbs?: Crumb[];
}) {
    return (
        <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="space-y-1.5">
                {breadcrumbs && breadcrumbs.length > 0 && (
                    <Breadcrumbs items={breadcrumbs} className="mb-3" />
                )}
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
            {actions && (
                <div className="flex shrink-0 flex-wrap gap-2">{actions}</div>
            )}
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
        <section id={id} className={cn('scroll-mt-32', className)}>
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
