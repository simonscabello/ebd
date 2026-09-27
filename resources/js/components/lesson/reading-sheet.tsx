import { BookOpen } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { PassageVerses, verseCount } from '@/components/lesson/passage-text';
import { BottomSheet } from '@/components/ui/bottom-sheet';
import type { LessonReading } from '@/types';

/**
 * Leitor da leitura do dia: o texto na largura toda, com tipografia de
 * leitura, e o rodapé (ex.: "Marcar como lido") fixo ao alcance do polegar.
 */
export function ReadingSheet({
    reading,
    open,
    onOpenChange,
    footer,
}: {
    reading: LessonReading | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    footer?: ReactNode;
}) {
    // Quem abre o leitor zera a leitura ao fechar. Guardamos a última para o
    // painel descer com o texto, e não encolhido só no título.
    const [lastReading, setLastReading] = useState(reading);

    if (reading !== null && reading !== lastReading) {
        setLastReading(reading);
    }

    const shown = reading ?? lastReading;
    const passage = shown?.passage;

    return (
        <BottomSheet
            open={open}
            onOpenChange={onOpenChange}
            title={shown?.reference ?? 'Leitura'}
            description={
                shown
                    ? [
                          shown.weekday_label ?? 'Leitura',
                          passage ? verseCount(passage) : null,
                      ]
                          .filter(Boolean)
                          .join(' · ')
                    : undefined
            }
            className="max-h-[92svh]"
        >
            {shown && (
                <div className="pb-6">
                    {shown.notes && (
                        <p className="mb-4 rounded-2xl bg-accent/60 px-4 py-3 text-sm text-pretty text-accent-foreground">
                            {shown.notes}
                        </p>
                    )}
                    {passage ? (
                        <PassageVerses passage={passage} size="reader" />
                    ) : (
                        <p className="text-muted-foreground">
                            Abra sua Bíblia em {shown.reference}.
                        </p>
                    )}
                    {footer && (
                        <div className="sticky bottom-0 -mx-5 mt-6 border-t bg-card px-5 pt-3 pb-4">
                            {footer}
                        </div>
                    )}
                </div>
            )}
        </BottomSheet>
    );
}

/**
 * Botão que abre o leitor, no card da leitura.
 */
export function ReadTextButton({
    reading,
    onClick,
}: {
    reading: LessonReading;
    onClick: () => void;
}) {
    if (!reading.passage || reading.passage.verses.length === 0) {
        return null;
    }

    return (
        <button
            type="button"
            onClick={onClick}
            className="mt-2 inline-flex min-h-9 items-center gap-1.5 rounded-lg text-sm font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <BookOpen className="size-4" />
            Ler o texto · {verseCount(reading.passage)}
        </button>
    );
}
