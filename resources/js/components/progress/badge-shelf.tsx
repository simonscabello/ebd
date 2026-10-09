import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type EarnedBadge = {
    badge: string;
    label: string;
    description: string;
    emoji: string;
    series: string | null;
    awarded_at: string;
};

export type AvailableBadge = Omit<EarnedBadge, 'series' | 'awarded_at'> & {
    progress?: { current: number; target: number } | null;
};

/**
 * Estante de selos: conquistados coloridos, os demais apagados como meta.
 */
export function BadgeShelf({
    earned,
    available,
}: {
    earned: EarnedBadge[];
    available: AvailableBadge[];
}) {
    const earnedKeys = new Set(earned.map((b) => b.badge));
    const missing = available.filter((b) => !earnedKeys.has(b.badge));

    return (
        <div className="grid gap-2 sm:grid-cols-2">
            {earned.map((badge, index) => (
                <BadgeCard key={`${badge.badge}-${index}`} badge={badge} earned>
                    {badge.series ? `${badge.series} · ` : ''}
                    {badge.awarded_at}
                </BadgeCard>
            ))}
            {missing.map((badge) => (
                <BadgeCard key={badge.badge} badge={badge} earned={false}>
                    {badge.description}
                    {badge.progress && badge.progress.target > 0 && (
                        <BadgeProgress {...badge.progress} />
                    )}
                </BadgeCard>
            ))}
        </div>
    );
}

/** "4 de 7" com uma barrinha: quanto falta para o selo. */
function BadgeProgress({
    current,
    target,
}: {
    current: number;
    target: number;
}) {
    return (
        <span className="mt-1.5 flex items-center gap-2">
            <span
                className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={target}
                aria-valuenow={current}
                aria-label="Progresso do selo"
            >
                <span
                    className="block h-full rounded-full bg-primary"
                    style={{
                        width: `${Math.min(100, (current / target) * 100)}%`,
                    }}
                />
            </span>
            <span className="shrink-0 font-medium text-foreground tabular-nums">
                {current} de {target}
            </span>
        </span>
    );
}

function BadgeCard({
    badge,
    earned,
    children,
}: {
    badge: AvailableBadge;
    earned: boolean;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'flex items-center gap-3 rounded-2xl border bg-card p-3',
                !earned && 'border-dashed',
            )}
        >
            <span
                className={cn('text-3xl', !earned && 'opacity-45 grayscale')}
                aria-hidden
            >
                {badge.emoji}
            </span>
            <div className="min-w-0 flex-1">
                <p
                    className={cn(
                        'font-medium',
                        !earned && 'text-muted-foreground',
                    )}
                >
                    {badge.label}
                </p>
                <div className="text-xs text-muted-foreground">{children}</div>
            </div>
        </div>
    );
}
