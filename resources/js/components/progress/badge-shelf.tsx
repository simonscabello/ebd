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

export type AvailableBadge = Omit<EarnedBadge, 'series' | 'awarded_at'>;

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
                </BadgeCard>
            ))}
        </div>
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
                !earned && 'opacity-55',
            )}
        >
            <span
                className={cn('text-3xl', !earned && 'grayscale')}
                aria-hidden
            >
                {badge.emoji}
            </span>
            <div className="min-w-0">
                <p className="font-medium">{badge.label}</p>
                <p className="text-xs text-muted-foreground">{children}</p>
            </div>
        </div>
    );
}
