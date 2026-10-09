import { cn } from '@/lib/utils';
import type { BiblePassage } from '@/types';

/**
 * Versículo-chave. O professor cadastra só a referência ("Jo 9.4-5"); quando
 * ela é reconhecida, o texto vem da Bíblia e a referência vira a assinatura.
 * Sem texto (referência não reconhecida ou Bíblia não importada), mostra o
 * que foi escrito, como antes.
 */
export function KeyVerseText({
    reference,
    passage,
    className,
    citeClassName,
}: {
    reference: string;
    passage: BiblePassage | null | undefined;
    className?: string;
    citeClassName?: string;
}) {
    if (!passage || passage.verses.length === 0) {
        return <span className={className}>{reference}</span>;
    }

    return (
        <span className={className}>
            “{passage.verses.map((verse) => verse.text).join(' ')}”{' '}
            <cite
                className={cn(
                    'font-sans text-[0.8em] font-medium whitespace-nowrap not-italic',
                    citeClassName,
                )}
            >
                ({passage.label})
            </cite>
        </span>
    );
}
