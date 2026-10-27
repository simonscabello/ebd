import {
    Award,
    BookOpenCheck,
    CalendarCheck,
    Flame,
    Sparkles,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Ícone de traço de cada selo, no lugar do emoji (que muda de aparelho para aparelho). */
const badgeIcons: Record<string, LucideIcon> = {
    first_full_week: BookOpenCheck,
    streak_7: Flame,
    streak_30: Sparkles,
    faithful_reader: Award,
    perfect_attendance: CalendarCheck,
};

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
    const Icon = badgeIcons[badge.badge] ?? Award;

    return (
        <div
            className={cn(
                'flex items-center gap-3 rounded-2xl border bg-card p-3',
                !earned && 'border-dashed',
            )}
        >
            <span
                className={cn(
                    'flex size-11 shrink-0 items-center justify-center rounded-xl',
                    earned
                        ? 'bg-accent text-primary'
                        : 'bg-muted text-muted-foreground',
                )}
                aria-hidden
            >
                <Icon className="size-5" />
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

/** Circunferência do anel de progresso (r = 26). */
const RING = 2 * Math.PI * 26;

/**
 * Selos em grade de ícones (Meu progresso): conquistados cheios, os outros
 * com um anel de quanto falta. Tocar num selo mostra como ganhá-lo.
 */
export function BadgeGrid({
    earned,
    available,
}: {
    earned: EarnedBadge[];
    available: AvailableBadge[];
}) {
    const [selected, setSelected] = useState<string | null>(null);
    const count = (key: string) => earned.filter((b) => b.badge === key).length;
    const chosen = available.find((b) => b.badge === selected) ?? null;
    const lastEarned = chosen
        ? earned.find((b) => b.badge === chosen.badge)
        : undefined;

    return (
        <div>
            <ul className="grid grid-cols-5 gap-1">
                {available.map((badge) => {
                    const times = count(badge.badge);
                    const Icon = badgeIcons[badge.badge] ?? Award;
                    const progress = badge.progress;
                    const ratio =
                        progress && progress.target > 0
                            ? Math.min(1, progress.current / progress.target)
                            : 0;

                    return (
                        <li key={badge.badge}>
                            <button
                                type="button"
                                onClick={() =>
                                    setSelected((current) =>
                                        current === badge.badge
                                            ? null
                                            : badge.badge,
                                    )
                                }
                                aria-pressed={selected === badge.badge}
                                aria-label={`${badge.label}: ${
                                    times > 0
                                        ? 'conquistado'
                                        : progress
                                          ? `${progress.current} de ${progress.target}`
                                          : 'ainda não'
                                }`}
                                className={cn(
                                    'flex w-full flex-col items-center gap-1.5 rounded-xl px-0.5 py-2 transition-colors hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    selected === badge.badge && 'bg-muted/60',
                                )}
                            >
                                <span className="relative flex size-14 items-center justify-center">
                                    {times === 0 && (
                                        <svg
                                            className="absolute inset-0 -rotate-90"
                                            viewBox="0 0 56 56"
                                            aria-hidden
                                        >
                                            <circle
                                                cx="28"
                                                cy="28"
                                                r="26"
                                                fill="none"
                                                strokeWidth="3"
                                                className="stroke-muted"
                                            />
                                            <circle
                                                cx="28"
                                                cy="28"
                                                r="26"
                                                fill="none"
                                                strokeWidth="3"
                                                strokeLinecap="round"
                                                strokeDasharray={`${ratio * RING} ${RING}`}
                                                className="stroke-primary"
                                            />
                                        </svg>
                                    )}
                                    <span
                                        className={cn(
                                            'flex items-center justify-center rounded-full',
                                            times > 0
                                                ? 'size-13 bg-primary text-primary-foreground'
                                                : 'size-11 bg-muted text-muted-foreground',
                                        )}
                                        aria-hidden
                                    >
                                        <Icon className="size-5" />
                                    </span>
                                    {times > 1 && (
                                        <span className="absolute -top-0.5 -right-0.5 rounded-full bg-foreground px-1.5 text-[10px] font-semibold text-background">
                                            ×{times}
                                        </span>
                                    )}
                                </span>
                                <span className="line-clamp-2 text-center text-[11px] leading-tight">
                                    {badge.label}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ul>
            {chosen && (
                <p className="mt-3 rounded-xl bg-muted/60 px-3 py-2.5 text-sm">
                    <span className="font-medium">{chosen.label}.</span>{' '}
                    <span className="text-muted-foreground">
                        {chosen.description}
                        {lastEarned
                            ? ` Conquistado em ${lastEarned.awarded_at}${lastEarned.series ? ` (${lastEarned.series})` : ''}.`
                            : chosen.progress && chosen.progress.target > 0
                              ? ` Até agora: ${chosen.progress.current} de ${chosen.progress.target}.`
                              : ''}
                    </span>
                </p>
            )}
        </div>
    );
}
