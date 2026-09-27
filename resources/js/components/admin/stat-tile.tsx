import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Um número da classe com o rótulo e, se preciso, o que ele significa.
 */
export function StatTile({
    icon,
    label,
    value,
    hint,
    tone = 'default',
}: {
    icon?: ReactNode;
    label: string;
    value: string;
    hint?: string;
    tone?: 'default' | 'warning';
}) {
    return (
        <div
            className={cn(
                'flex items-center gap-4 rounded-2xl border bg-card p-4 sm:block',
                tone === 'warning' && 'border-warning/50 bg-warning-soft',
            )}
        >
            {icon && (
                <span className="hidden text-primary sm:block [&_svg]:size-5">
                    {icon}
                </span>
            )}
            <p className="w-20 shrink-0 font-serif text-3xl font-semibold tabular-nums sm:mt-1.5 sm:w-auto">
                {value}
            </p>
            <div className="min-w-0">
                <p className="text-sm font-medium">{label}</p>
                {hint && (
                    <p className="text-xs text-muted-foreground">{hint}</p>
                )}
            </div>
        </div>
    );
}
