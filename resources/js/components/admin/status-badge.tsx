import { cn } from '@/lib/utils';
import type { LessonStatus } from '@/types';

const styles: Record<LessonStatus, string> = {
    draft: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    published:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
};

export function StatusBadge({
    status,
    label,
    className,
}: {
    status: LessonStatus;
    label: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                styles[status],
                className,
            )}
        >
            {label}
        </span>
    );
}
