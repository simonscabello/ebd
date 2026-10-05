import { useEffect, useState } from 'react';
import type { MouseEvent } from 'react';
import { PassageVerses } from '@/components/lesson/passage-text';
import { BottomSheet } from '@/components/ui/bottom-sheet';
import { Spinner } from '@/components/ui/spinner';
import { show } from '@/routes/bible';
import type { BiblePassage } from '@/types';

type Found = { reference: string; passage: BiblePassage | null };

type State =
    | { status: 'loading' }
    | { status: 'done'; passages: Found[] }
    | { status: 'error' };

/**
 * HTML de uma lição gerado no servidor (Markdown com HTML bruto removido).
 * As referências bíblicas do texto chegam como botões `.bible-ref` (ver
 * App\Support\Bible\BibleLinks); tocar numa abre o painel com o trecho e,
 * se ela estiver numa sequência ("At 5.12-13; At 8.6-8"), com todos juntos.
 */
export function RichText({
    html,
    className,
}: {
    html: string;
    className?: string;
}) {
    const [group, setGroup] = useState<string | null>(null);
    const [open, setOpen] = useState(false);

    const handleClick = (event: MouseEvent<HTMLDivElement>) => {
        const button = (event.target as HTMLElement).closest<HTMLElement>(
            'button.bible-ref[data-bible]',
        );

        if (!button?.dataset.bible) {
            return;
        }

        event.preventDefault();
        setGroup(button.dataset.bible);
        setOpen(true);
    };

    return (
        <>
            <div
                className={className}
                onClick={handleClick}
                dangerouslySetInnerHTML={{ __html: html }}
            />
            <BibleReferenceSheet
                group={group}
                open={open}
                onOpenChange={setOpen}
            />
        </>
    );
}

function BibleReferenceSheet({
    group,
    open,
    onOpenChange,
}: {
    group: string | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [loaded, setLoaded] = useState<{ group: string; state: State }>();

    // Busca ao abrir um grupo novo; o mesmo grupo de novo usa o que já veio.
    useEffect(() => {
        if (group === null) {
            return;
        }

        const controller = new AbortController();

        fetch(show.url({ query: { ref: group } }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((data: { passages: Found[] } | null) =>
                setLoaded({
                    group,
                    state: data
                        ? { status: 'done', passages: data.passages }
                        : { status: 'error' },
                }),
            )
            .catch(() => {
                if (!controller.signal.aborted) {
                    setLoaded({ group, state: { status: 'error' } });
                }
            });

        return () => controller.abort();
    }, [group]);

    const references = group ? group.split('|') : [];
    const state: State =
        loaded && loaded.group === group ? loaded.state : { status: 'loading' };
    const passages = state.status === 'done' ? state.passages : [];
    const credit = passages.find((item) => item.passage)?.passage?.credit;

    return (
        <BottomSheet
            open={open}
            onOpenChange={onOpenChange}
            title={
                references.length > 1
                    ? `${references.length} referências`
                    : (references[0] ?? 'Referência')
            }
            description={
                references.length > 1 ? references.join(' · ') : undefined
            }
            className="max-h-[92svh]"
        >
            <div className="pb-6">
                {state.status === 'loading' && (
                    <p className="flex items-center gap-2 py-6 text-muted-foreground">
                        <Spinner /> Abrindo o texto…
                    </p>
                )}
                {state.status === 'error' && (
                    <p className="py-4 text-muted-foreground">
                        Não deu para carregar o texto agora. Abra sua Bíblia em{' '}
                        {references.join('; ')}.
                    </p>
                )}
                {state.status === 'done' && (
                    <div className="space-y-6">
                        {passages.map((item) => (
                            <section key={item.reference}>
                                {passages.length > 1 && (
                                    <h3 className="mb-2 font-sans text-sm font-semibold tracking-tight text-primary">
                                        {item.passage?.label ?? item.reference}
                                    </h3>
                                )}
                                {item.passage ? (
                                    <PassageVerses
                                        passage={item.passage}
                                        size="reader"
                                        credit={false}
                                    />
                                ) : (
                                    <p className="text-muted-foreground">
                                        Abra sua Bíblia em {item.reference}.
                                    </p>
                                )}
                            </section>
                        ))}
                        {credit && (
                            <p className="text-xs text-muted-foreground">
                                {credit}
                            </p>
                        )}
                    </div>
                )}
            </div>
        </BottomSheet>
    );
}
