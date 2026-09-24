import { CircleAlert, CircleCheck } from 'lucide-react';
import { useBiblePreview } from '@/hooks/use-bible-preview';

/**
 * Mostra como uma referência digitada foi entendida e o começo do texto, para
 * o professor perceber na hora um erro de digitação.
 */
export function BiblePreview({ reference }: { reference: string }) {
    const result = useBiblePreview(reference);

    if (result.status === 'idle' || result.status === 'loading') {
        return null;
    }

    if (result.status === 'missing') {
        return (
            <p className="flex items-start gap-1.5 text-sm text-amber-700 dark:text-amber-400">
                <CircleAlert className="mt-0.5 size-4 shrink-0" />
                <span>
                    Referência não reconhecida: o texto não vai aparecer para a
                    classe. Confira o formato, ex.: Lucas 5.12-16.
                </span>
            </p>
        );
    }

    const { passage } = result;
    const count = passage.verses.length;

    return (
        <div className="text-sm">
            <p className="flex items-center gap-1.5 font-medium text-success-foreground">
                <CircleCheck className="size-4 shrink-0" />
                {passage.label} · {count}{' '}
                {count === 1 ? 'versículo' : 'versículos'} ({passage.version})
            </p>
            <p className="mt-1 line-clamp-2 font-serif text-muted-foreground">
                {passage.verses[0].text}
            </p>
        </div>
    );
}
