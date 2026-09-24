import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

export type SectionLink = { id: string; label: string };

/**
 * Navegação fixa entre as seções de uma página longa. Destaca a seção que está
 * na tela e mostra o quanto da página já foi lido.
 */
export function SectionNav({
    sections,
    label,
    progress = false,
    className,
}: {
    sections: SectionLink[];
    label: string;
    /** Mostra a barra de progresso de leitura. */
    progress?: boolean;
    className?: string;
}) {
    const [active, setActive] = useState<string | null>(null);
    const [read, setRead] = useState(0);
    const nav = useRef<HTMLElement>(null);

    useEffect(() => {
        const elements = sections
            .map((section) => document.getElementById(section.id))
            .filter((element): element is HTMLElement => element !== null);

        if (elements.length === 0) {
            return;
        }

        const update = () => {
            // Seção ativa: a última cujo topo já passou da linha de leitura.
            // No fim da página, a última seção (que talvez nunca chegue à
            // linha) é a ativa.
            const line = window.innerHeight * 0.4;
            const atBottom =
                window.innerHeight + window.scrollY >=
                document.documentElement.scrollHeight - 4;
            let current: string | null = elements[0].id;

            for (const element of elements) {
                if (element.getBoundingClientRect().top <= line) {
                    current = element.id;
                }
            }

            setActive(atBottom ? elements[elements.length - 1].id : current);

            if (progress) {
                const total =
                    document.documentElement.scrollHeight - window.innerHeight;
                setRead(total > 0 ? Math.min(1, window.scrollY / total) : 1);
            }
        };

        update();
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);

        return () => {
            window.removeEventListener('scroll', update);
            window.removeEventListener('resize', update);
        };
    }, [sections, progress]);

    // Mantém o item ativo visível na faixa rolável.
    useEffect(() => {
        if (!active || !nav.current) {
            return;
        }

        const link = nav.current.querySelector<HTMLElement>(
            `[data-section="${active}"]`,
        );
        link?.scrollIntoView({
            block: 'nearest',
            inline: 'nearest',
            behavior: 'smooth',
        });
    }, [active]);

    return (
        <nav
            ref={nav}
            aria-label={label}
            className={cn(
                'sticky top-16 z-20 -mx-4 border-b border-border/60 bg-background/90 backdrop-blur supports-[backdrop-filter]:bg-background/80',
                className,
            )}
        >
            <ul className="flex [scrollbar-width:none] gap-2 overflow-x-auto px-4 py-2.5">
                {sections.map((section) => {
                    const isActive = section.id === active;

                    return (
                        <li key={section.id} className="shrink-0">
                            <a
                                href={`#${section.id}`}
                                data-section={section.id}
                                aria-current={isActive ? 'location' : undefined}
                                className={cn(
                                    'inline-flex min-h-9 items-center rounded-full px-3.5 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    isActive
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {section.label}
                            </a>
                        </li>
                    );
                })}
            </ul>
            {progress && (
                <div
                    className="h-0.5 w-full bg-transparent"
                    role="progressbar"
                    aria-label="Progresso da leitura"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={Math.round(read * 100)}
                >
                    <div
                        className="h-0.5 bg-primary transition-[width] duration-150"
                        style={{ width: `${read * 100}%` }}
                    />
                </div>
            )}
        </nav>
    );
}
