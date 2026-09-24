import { ChevronDown } from 'lucide-react';
import { useId, useState } from 'react';
import { cn } from '@/lib/utils';
import type { BiblePassage } from '@/types';

/**
 * O texto de uma passagem, como numa Bíblia: um parágrafo corrido por
 * capítulo, com o número do versículo em sobrescrito e o crédito da versão.
 *
 * Trechos longos começam fechados ("Ler o texto"), para a referência continuar
 * sendo o que se vê primeiro. Sem passagem (referência não reconhecida ou
 * texto ainda não importado) não renderiza nada.
 */
export function PassageText({
    passage,
    size = 'default',
    defaultOpen,
    className,
}: {
    passage: BiblePassage | null | undefined;
    size?: 'default' | 'large';
    /** Padrão: aberto quando o trecho é curto. */
    defaultOpen?: boolean;
    className?: string;
}) {
    const id = useId();
    const [open, setOpen] = useState(
        defaultOpen ?? (passage ? passage.verses.length <= 8 : false),
    );

    if (!passage || passage.verses.length === 0) {
        return null;
    }

    const count = passage.verses.length;
    const chapters = groupByChapter(passage);
    const multiChapter = chapters.length > 1;

    return (
        <div className={cn('mt-3', className)}>
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                aria-controls={id}
                className="flex min-h-9 items-center gap-1 rounded-lg text-sm font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <ChevronDown
                    className={cn(
                        'size-4 transition-transform',
                        open && 'rotate-180',
                    )}
                />
                {open
                    ? 'Ocultar o texto'
                    : `Ler o texto · ${count} ${count === 1 ? 'versículo' : 'versículos'}`}
            </button>
            {open && (
                <div id={id} className="mt-2 space-y-3">
                    {chapters.map(({ chapter, verses }) => (
                        <p
                            key={chapter}
                            className={cn(
                                'font-serif leading-relaxed text-pretty',
                                size === 'large'
                                    ? 'text-[1.1em] md:text-[1.2em]'
                                    : 'text-[1.05rem]',
                            )}
                        >
                            {verses.map((verse, index) => (
                                <span key={verse.verse}>
                                    {index > 0 && ' '}
                                    <sup className="mr-0.5 text-[0.65em] font-semibold text-primary select-none">
                                        {multiChapter && index === 0
                                            ? `${chapter}.${verse.verse}`
                                            : verse.verse}
                                    </sup>
                                    {verse.text}
                                </span>
                            ))}
                        </p>
                    ))}
                    <p className="text-xs text-muted-foreground">
                        {passage.credit}
                    </p>
                </div>
            )}
        </div>
    );
}

function groupByChapter(passage: BiblePassage) {
    const groups: { chapter: number; verses: BiblePassage['verses'] }[] = [];

    for (const verse of passage.verses) {
        const last = groups[groups.length - 1];

        if (last && last.chapter === verse.chapter) {
            last.verses.push(verse);
        } else {
            groups.push({ chapter: verse.chapter, verses: [verse] });
        }
    }

    return groups;
}
