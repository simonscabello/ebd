import { useState } from 'react';
import { BlockIcon } from '@/components/lesson/block-icon';
import { RichText } from '@/components/lesson/rich-text';
import { cn } from '@/lib/utils';
import type { LessonBlock } from '@/types';

/**
 * "Para hoje" no Início (e o conteúdo de cada dia em Minha semana): a
 * curiosidade ou o conceito liberado no dia, em card curto. O texto abre por
 * inteiro no próprio card.
 */
export function DailyBlocks({
    blocks,
    label = 'Para hoje',
    className = 'mt-4',
}: {
    blocks: LessonBlock[];
    label?: string;
    className?: string;
}) {
    return (
        <div className={cn('space-y-3', className)}>
            {blocks.map((block) => (
                <DailyBlock key={block.id} block={block} label={label} />
            ))}
        </div>
    );
}

function DailyBlock({ block, label }: { block: LessonBlock; label: string }) {
    const [open, setOpen] = useState(false);

    return (
        <article className="rounded-2xl border border-highlight bg-highlight/40 p-4">
            <p className="flex items-center gap-2 text-xs font-medium text-highlight-foreground">
                <BlockIcon kind={block.kind} className="size-4" />
                {label} · {block.kind_label}
            </p>
            <h2 className="mt-1 font-serif text-lg leading-snug font-semibold text-balance">
                {block.display_title}
            </h2>
            {block.body_html && (
                <>
                    <RichText
                        className={cn(
                            'reading mt-2 text-base [&>:first-child]:mt-0',
                            !open && 'line-clamp-3',
                        )}
                        html={block.body_html}
                    />
                    <button
                        type="button"
                        onClick={() => setOpen((value) => !value)}
                        aria-expanded={open}
                        className="mt-2 min-h-9 rounded-lg text-sm font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {open ? 'Mostrar menos' : 'Ler tudo'}
                    </button>
                </>
            )}
        </article>
    );
}
