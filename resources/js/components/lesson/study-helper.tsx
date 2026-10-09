import { MessageCircleQuestion, SendHorizontal } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode, Ref } from 'react';
import { BottomSheet } from '@/components/ui/bottom-sheet';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { plural, xsrfToken } from '@/lib/utils';
import { store } from '@/routes/lessons/questions';
import type { Lesson } from '@/types';

export type StudyHelperInfo = {
    remaining: number;
    daily_limit: number;
};

type Exchange = { question: string; answer: string };

const MAX_LENGTH = 500;

function suggestionsFor(lesson: Lesson): string[] {
    return [
        lesson.key_verse && `Explique o versículo-chave (${lesson.key_verse}).`,
        'Resuma o estudo em três pontos.',
        lesson.bible_reference &&
            `Qual é o contexto histórico de ${lesson.bible_reference}?`,
        'Como aplicar esta lição na minha semana?',
    ].filter(Boolean) as string[];
}

/**
 * "Tirar dúvida": painel em que o membro da classe pergunta à IA sobre a
 * lição. Cada pergunta vai sozinha; as respostas desta visita ficam listadas
 * no painel, mas não viram conversa.
 */
export function StudyHelperSheet({
    lesson,
    helper,
    open,
    onOpenChange,
}: {
    lesson: Lesson;
    helper: StudyHelperInfo;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [question, setQuestion] = useState('');
    const [exchanges, setExchanges] = useState<Exchange[]>([]);
    const [pending, setPending] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [remaining, setRemaining] = useState(helper.remaining);
    const latest = useRef<HTMLDivElement>(null);

    // Leva ao começo da última pergunta, para a resposta ser lida do início.
    useEffect(() => {
        latest.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, [exchanges.length, pending]);

    const ask = async (text: string) => {
        const trimmed = text.trim();

        if (trimmed.length < 3 || pending !== null || remaining === 0) {
            return;
        }

        setPending(trimmed);
        setError(null);
        setQuestion('');

        try {
            const response = await fetch(store.url(lesson.slug), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ question: trimmed }),
            });

            const data = await response.json().catch(() => null);

            if (!response.ok) {
                setQuestion(trimmed);
                setError(
                    data?.errors?.question?.[0] ??
                        (response.status === 429
                            ? 'Muitas perguntas seguidas. Espere um minuto e tente de novo.'
                            : 'Não consegui responder agora. Tente de novo em alguns minutos.'),
                );

                return;
            }

            setExchanges((list) => [
                ...list,
                { question: trimmed, answer: data.answer },
            ]);
            setRemaining(data.remaining);
        } catch {
            setQuestion(trimmed);
            setError('Sem conexão. Confira a internet e tente de novo.');
        } finally {
            setPending(null);
        }
    };

    const exhausted = remaining === 0;

    return (
        <BottomSheet
            open={open}
            onOpenChange={onOpenChange}
            title="Tirar dúvida"
            description="A IA responde com base nesta lição."
            className="max-h-[92svh]"
        >
            <div className="space-y-5 pb-2">
                {exchanges.length === 0 && pending === null && (
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Pergunte sobre o estudo, uma palavra difícil, um
                            personagem ou um versículo. Algumas ideias:
                        </p>
                        <div className="mt-3 flex flex-col items-start gap-2">
                            {suggestionsFor(lesson).map((suggestion) => (
                                <button
                                    key={suggestion}
                                    type="button"
                                    disabled={exhausted}
                                    onClick={() => ask(suggestion)}
                                    className="rounded-2xl border bg-background px-3.5 py-2 text-left text-sm text-pretty hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
                                >
                                    {suggestion}
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {exchanges.map((exchange, index) => (
                    <ExchangeBubble
                        key={index}
                        ref={
                            pending === null && index === exchanges.length - 1
                                ? latest
                                : undefined
                        }
                        question={exchange.question}
                    >
                        {exchange.answer
                            .split(/\n\s*\n/)
                            .map((paragraph, i) => (
                                <p key={i}>{paragraph}</p>
                            ))}
                    </ExchangeBubble>
                ))}

                {pending !== null && (
                    <ExchangeBubble ref={latest} question={pending}>
                        <p
                            className="flex items-center gap-2 text-muted-foreground"
                            aria-live="polite"
                        >
                            <Spinner /> Pensando na resposta…
                        </p>
                    </ExchangeBubble>
                )}

                <p className="text-xs text-muted-foreground">
                    A IA pode errar e não substitui o professor nem a aula.
                    Confira na Bíblia e leve a dúvida para domingo.
                </p>
            </div>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    void ask(question);
                }}
                className="sticky bottom-0 -mx-5 border-t bg-card px-5 pt-3 pb-4"
            >
                {error && (
                    <p role="alert" className="mb-2 text-sm text-destructive">
                        {error}
                    </p>
                )}
                <div className="flex items-end gap-2">
                    <Textarea
                        value={question}
                        onChange={(event) => setQuestion(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' && !event.shiftKey) {
                                event.preventDefault();
                                void ask(question);
                            }
                        }}
                        rows={2}
                        maxLength={MAX_LENGTH}
                        disabled={exhausted}
                        placeholder={
                            exhausted
                                ? 'Acabaram as perguntas de hoje.'
                                : 'Escreva a sua dúvida…'
                        }
                        aria-label="Sua dúvida sobre a lição"
                        className="min-h-0 resize-none"
                    />
                    <Button
                        type="submit"
                        size="icon"
                        className="size-11 shrink-0"
                        disabled={
                            pending !== null ||
                            exhausted ||
                            question.trim().length < 3
                        }
                        aria-label="Enviar dúvida"
                    >
                        <SendHorizontal />
                    </Button>
                </div>
                <p className="mt-2 text-xs text-muted-foreground">
                    {exhausted
                        ? 'Você usou as perguntas de hoje. Amanhã tem mais!'
                        : `${plural(remaining, 'pergunta restante', 'perguntas restantes')} hoje.`}
                </p>
            </form>
        </BottomSheet>
    );
}

function ExchangeBubble({
    ref,
    question,
    children,
}: {
    ref?: Ref<HTMLDivElement>;
    question: string;
    children: ReactNode;
}) {
    return (
        <div ref={ref} className="scroll-mt-2 space-y-3">
            <p className="ml-auto w-fit max-w-[85%] rounded-2xl rounded-br-md bg-primary px-4 py-2.5 text-sm text-pretty text-primary-foreground">
                {question}
            </p>
            <div className="space-y-3 rounded-2xl rounded-bl-md bg-muted/70 px-4 py-3 text-[0.95rem] leading-relaxed text-pretty">
                {children}
            </div>
        </div>
    );
}

/** Chamada no fim do estudo para abrir o painel. */
export function StudyHelperPrompt({ onOpen }: { onOpen: () => void }) {
    return (
        <button
            type="button"
            onClick={onOpen}
            className="mt-8 flex w-full items-center gap-4 rounded-2xl border bg-card px-4 py-4 text-left hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                <MessageCircleQuestion className="size-5" />
            </span>
            <span className="min-w-0">
                <span className="block font-medium">
                    Ficou com alguma dúvida?
                </span>
                <span className="block text-sm text-muted-foreground">
                    Pergunte à IA sobre esta lição.
                </span>
            </span>
        </button>
    );
}

/**
 * Botão flutuante, sempre à mão durante a leitura. Fica acima do menu
 * inferior e do mini player do áudio, quando ele aparece.
 */
export function StudyHelperButton({ onOpen }: { onOpen: () => void }) {
    return (
        <button
            type="button"
            onClick={onOpen}
            className="fixed right-4 bottom-[calc(var(--app-bottom-nav)+var(--app-mini-player,0px)+1rem)] z-30 inline-flex h-12 items-center gap-2 rounded-full bg-primary pr-5 pl-4 font-medium text-primary-foreground shadow-lg transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none md:right-6 md:bottom-[calc(var(--app-mini-player,0px)+1.5rem)] print:hidden"
        >
            <MessageCircleQuestion className="size-5" /> Tirar dúvida
        </button>
    );
}
