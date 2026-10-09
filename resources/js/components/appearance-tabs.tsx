import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

export default function AppearanceToggleTab({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Claro' },
        { value: 'dark', icon: Moon, label: 'Escuro' },
        { value: 'system', icon: Monitor, label: 'Sistema' },
    ];

    return (
        <div
            role="radiogroup"
            aria-label="Aparência"
            className={cn(
                'grid grid-cols-3 gap-1 rounded-2xl border bg-card p-1',
                className,
            )}
            {...props}
        >
            {tabs.map(({ value, icon: Icon, label }) => (
                <button
                    key={value}
                    type="button"
                    role="radio"
                    aria-checked={appearance === value}
                    onClick={() => updateAppearance(value)}
                    className={cn(
                        'flex min-h-10 items-center justify-center gap-1.5 rounded-xl px-3 text-sm font-medium transition-colors',
                        appearance === value
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                    )}
                >
                    <Icon className="size-4" />
                    {label}
                </button>
            ))}
        </div>
    );
}
