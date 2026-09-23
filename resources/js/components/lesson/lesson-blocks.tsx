import { ChevronDown } from 'lucide-react';
import { BlockIcon } from '@/components/lesson/block-icon';
import { cn } from '@/lib/utils';
import type { LessonBlock } from '@/types';

/**
 * Blocos em cartões (contexto, teologia, aplicação, curiosidades, roteiro...).
 * O HTML vem do servidor (Markdown com HTML bruto removido).
 */
export function BlockCards({
    blocks,
    tone = 'default',
    size = 'default',
}: {
    blocks: LessonBlock[];
    tone?: 'default' | 'highlight' | 'teacher';
    size?: 'default' | 'large';
}) {
    return (
        <div className="space-y-4">
            {blocks.map((block) => (
                <article
                    key={block.id}
                    className={cn(
                        'rounded-2xl border p-5',
                        tone === 'default' && 'bg-card',
                        tone === 'highlight' &&
                            'border-highlight bg-highlight/40',
                        tone === 'teacher' && 'border-dashed bg-card',
                    )}
                >
                    <p className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <BlockIcon
                            kind={block.kind}
                            className="size-4 text-primary"
                        />
                        {block.title ? block.kind_label : null}
                    </p>
                    <h3 className="mt-1 font-serif text-xl font-semibold tracking-tight text-balance">
                        {block.display_title}
                    </h3>
                    {block.body_html && (
                        <div
                            className={cn(
                                'reading mt-3',
                                size === 'default' && 'text-base',
                            )}
                            dangerouslySetInnerHTML={{
                                __html: block.body_html,
                            }}
                        />
                    )}
                </article>
            ))}
        </div>
    );
}

/**
 * Blocos recolhíveis (conceitos, "se houver tempo"): o título abre o conteúdo.
 */
export function BlockAccordion({
    blocks,
    defaultOpen = false,
}: {
    blocks: LessonBlock[];
    defaultOpen?: boolean;
}) {
    return (
        <div className="divide-y rounded-2xl border bg-card">
            {blocks.map((block) => (
                <details key={block.id} open={defaultOpen} className="group">
                    <summary className="flex cursor-pointer list-none items-center gap-3 px-4 py-3.5 font-medium [&::-webkit-details-marker]:hidden">
                        <BlockIcon
                            kind={block.kind}
                            className="size-5 shrink-0 text-primary"
                        />
                        <span className="flex-1">{block.display_title}</span>
                        <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180" />
                    </summary>
                    {block.body_html && (
                        <div
                            className="reading px-4 pb-5 text-base"
                            dangerouslySetInnerHTML={{
                                __html: block.body_html,
                            }}
                        />
                    )}
                </details>
            ))}
        </div>
    );
}

/**
 * Separa os blocos do aluno nas seções da página da lição.
 */
export function groupStudentBlocks(blocks: LessonBlock[]) {
    return {
        deepen: blocks.filter((b) =>
            ['context', 'theology', 'application'].includes(b.kind),
        ),
        curiosities: blocks.filter((b) => b.kind === 'curiosity'),
        concepts: blocks.filter((b) => b.kind === 'concept'),
        // Tipos do professor marcados como "alunos" (ex.: nota de precisão aberta).
        other: blocks.filter(
            (b) =>
                ![
                    'context',
                    'theology',
                    'application',
                    'curiosity',
                    'concept',
                ].includes(b.kind),
        ),
    };
}
