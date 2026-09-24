import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Grupo de opções do perfil: título e um card com as linhas.
 */
export function SettingsGroup({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-2.5">
            <div>
                <h2 className="font-semibold tracking-tight">{title}</h2>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}

export function SettingsCard({ children }: { children: ReactNode }) {
    return (
        <div className="divide-y overflow-hidden rounded-2xl border bg-card">
            {children}
        </div>
    );
}

type RowProps = {
    icon: ReactNode;
    title: string;
    description?: ReactNode;
    trailing?: ReactNode;
    tone?: 'default' | 'destructive';
};

function RowContent({ icon, title, description, trailing, tone }: RowProps) {
    return (
        <>
            <span
                className={cn(
                    'shrink-0 text-muted-foreground [&_svg]:size-5',
                    tone === 'destructive' && 'text-destructive',
                )}
            >
                {icon}
            </span>
            <span className="min-w-0 flex-1">
                <span
                    className={cn(
                        'block font-medium',
                        tone === 'destructive' && 'text-destructive',
                    )}
                >
                    {title}
                </span>
                {description && (
                    <span className="block text-sm text-muted-foreground">
                        {description}
                    </span>
                )}
            </span>
            {trailing}
        </>
    );
}

const rowClass =
    'flex w-full items-center gap-3.5 px-4 py-3.5 text-left transition-colors';

/**
 * Linha do perfil. Com href vira link (com seta); com onClick, botão.
 */
export function SettingsRow({
    href,
    method,
    onClick,
    ...props
}: RowProps & {
    href?: string;
    method?: 'post';
    onClick?: () => void;
}) {
    if (href) {
        return (
            <Link
                href={href}
                method={method}
                as={method ? 'button' : undefined}
                className={cn(rowClass, 'hover:bg-muted/50')}
            >
                <RowContent
                    {...props}
                    trailing={
                        props.trailing ??
                        (method ? null : (
                            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                        ))
                    }
                />
            </Link>
        );
    }

    if (onClick) {
        return (
            <button
                type="button"
                onClick={onClick}
                className={cn(rowClass, 'hover:bg-muted/50')}
            >
                <RowContent {...props} />
            </button>
        );
    }

    return (
        <div className={rowClass}>
            <RowContent {...props} />
        </div>
    );
}

/**
 * Cabeçalho das telas abertas a partir do perfil, com o caminho de volta.
 */
export function SubpageHeader({
    backHref,
    title,
    description,
}: {
    backHref: string;
    title: string;
    description?: string;
}) {
    return (
        <header className="mb-6">
            <Link
                href={backHref}
                className="-ml-1 inline-flex items-center gap-1 rounded-lg px-1 py-1 text-sm font-medium text-muted-foreground hover:text-foreground"
            >
                <ChevronLeft className="size-4" /> Perfil
            </Link>
            <h1 className="mt-2 font-serif text-3xl font-semibold tracking-tight">
                {title}
            </h1>
            {description && (
                <p className="mt-1 text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
