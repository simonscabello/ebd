import { BookOpen } from 'lucide-react';
import { cn } from '@/lib/utils';

export function BiblePassage({
    reference,
    size = 'default',
}: {
    reference: string;
    size?: 'default' | 'large';
}) {
    return (
        <div className="rounded-2xl border border-primary/15 bg-accent/50 p-5">
            <p className="flex items-center gap-2 text-sm font-medium text-accent-foreground">
                <BookOpen className="size-4" /> Texto base
            </p>
            <p
                className={cn(
                    'mt-1 font-serif font-semibold tracking-tight',
                    size === 'large' ? 'text-3xl md:text-4xl' : 'text-2xl',
                )}
            >
                {reference}
            </p>
        </div>
    );
}
