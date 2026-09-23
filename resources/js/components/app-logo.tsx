import { BookOpen } from 'lucide-react';
import { cn } from '@/lib/utils';

export function AppLogoMark({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground',
                className,
            )}
            aria-hidden
        >
            <BookOpen className="size-5" strokeWidth={2.2} />
        </span>
    );
}

export default function AppLogo({ churchName }: { churchName?: string }) {
    return (
        <span className="flex items-center gap-2.5">
            <AppLogoMark />
            <span className="flex flex-col leading-tight">
                <span className="text-[15px] font-semibold tracking-tight">
                    EBD
                </span>
                {churchName && (
                    <span className="max-w-[14rem] truncate text-xs text-muted-foreground">
                        {churchName}
                    </span>
                )}
            </span>
        </span>
    );
}
