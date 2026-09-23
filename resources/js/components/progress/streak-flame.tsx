import { Flame } from 'lucide-react';
import { cn } from '@/lib/utils';

export type Streak = { current: number; best: number; today_done: boolean };

/**
 * Sequência de dias de estudo. Discreta: é só da própria pessoa.
 */
export function StreakFlame({ streak }: { streak: Streak }) {
    const active = streak.current > 0;

    return (
        <div className="flex items-center gap-3 rounded-2xl border bg-card p-4">
            <span
                className={cn(
                    'flex size-12 items-center justify-center rounded-full',
                    active
                        ? 'bg-orange-100 text-orange-600 dark:bg-orange-950 dark:text-orange-400'
                        : 'bg-muted text-muted-foreground',
                )}
            >
                <Flame className="size-6" />
            </span>
            <div>
                <p className="font-serif text-2xl leading-none font-semibold">
                    {streak.current} {streak.current === 1 ? 'dia' : 'dias'}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    {active
                        ? streak.today_done
                            ? 'de estudo seguidos. Hoje já está feito!'
                            : 'seguidos. Leia hoje para manter a sequência.'
                        : 'Comece hoje a sua sequência de estudo.'}
                    {streak.best > streak.current &&
                        ` Recorde: ${streak.best}.`}
                </p>
            </div>
        </div>
    );
}
