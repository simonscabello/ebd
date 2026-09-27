import { cn } from '@/lib/utils';
import type { MeetingStatus } from '@/types';

const styles: Record<MeetingStatus, string> = {
    planned: 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
    held: 'bg-muted text-muted-foreground',
    cancelled: 'bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-200',
};

/**
 * Situação do domingo: Planejado, Realizado ou Sem EBD.
 */
export function MeetingStatusBadge({
    status,
    label,
    className,
}: {
    status: MeetingStatus;
    label: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                styles[status],
                className,
            )}
        >
            {label}
        </span>
    );
}
