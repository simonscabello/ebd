import { useState } from 'react';
import { RichText } from '@/components/lesson/rich-text';
import { BottomSheet } from '@/components/ui/bottom-sheet';
import type { LessonBlock } from '@/types';

/**
 * O conteúdo de um dia (curiosidade, conceito…) aberto por cima da lista, na
 * largura toda: a Semana fica só com as linhas, como nas leituras.
 */
export function BlockSheet({
    block,
    label,
    open,
    onOpenChange,
}: {
    block: LessonBlock | null;
    /** O dia: "Para hoje", "Quarta-feira"… */
    label: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    // Quem abre zera o bloco ao fechar; guardamos o último para o painel
    // descer com o texto, e não vazio.
    const [last, setLast] = useState(block);

    if (block !== null && block !== last) {
        setLast(block);
    }

    const shown = block ?? last;

    return (
        <BottomSheet
            open={open}
            onOpenChange={onOpenChange}
            title={shown?.display_title ?? 'Conteúdo do dia'}
            description={shown ? `${label} · ${shown.kind_label}` : undefined}
            className="max-h-[92svh]"
        >
            {shown?.body_html && (
                <RichText
                    className="reading pb-6 [&>:first-child]:mt-0"
                    html={shown.body_html}
                />
            )}
        </BottomSheet>
    );
}
