import { cn } from '@/lib/utils';

/**
 * Barra de 0 a 100% (frequência, estudo em casa).
 */
export function Meter({
    value,
    label,
    className,
}: {
    value: number | null;
    label?: string;
    className?: string;
}) {
    return (
        <div
            className={cn('h-2 rounded-full bg-muted', className)}
            role={label ? 'img' : undefined}
            aria-label={label}
            aria-hidden={label ? undefined : true}
        >
            <div
                className="h-2 rounded-full bg-primary"
                style={{ width: `${Math.min(100, Math.max(0, value ?? 0))}%` }}
            />
        </div>
    );
}
