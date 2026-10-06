import { Bold, Heading2, Heading3, Italic, List, Quote } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { RichText } from '@/components/lesson/rich-text';
import { preview } from '@/routes/admin/markdown';

type Props = {
    id: string;
    value: string;
    onChange: (value: string) => void;
    rows?: number;
    maxLength?: number;
    placeholder?: string;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
};

type Edit = { text: string; start: number; end: number };

/** Envolve a seleção (ex.: **negrito**); sem seleção, insere o par e põe o cursor no meio. */
function wrap(edit: Edit, mark: string): Edit {
    const { text, start, end } = edit;
    const selected = text.slice(start, end);

    return {
        text: text.slice(0, start) + mark + selected + mark + text.slice(end),
        start: start + mark.length,
        end: end + mark.length,
    };
}

/** Põe o prefixo (ex.: "## ") no começo de cada linha da seleção. */
function prefixLines(edit: Edit, prefix: string): Edit {
    const { text, start, end } = edit;
    const lineStart = text.lastIndexOf('\n', start - 1) + 1;
    const block = text.slice(lineStart, end);
    const lines = block.split('\n');
    const prefixed = lines
        .map((line) => (line.startsWith(prefix) ? line : prefix + line))
        .join('\n');

    return {
        text: text.slice(0, lineStart) + prefixed + text.slice(end),
        start: start + prefix.length,
        end: end + (prefixed.length - block.length),
    };
}

const TOOLS: {
    label: string;
    icon: ReactNode;
    apply: (edit: Edit) => Edit;
}[] = [
    {
        label: 'Título de tópico',
        icon: <Heading2 />,
        apply: (e) => prefixLines(e, '## '),
    },
    {
        label: 'Subtítulo',
        icon: <Heading3 />,
        apply: (e) => prefixLines(e, '### '),
    },
    { label: 'Negrito', icon: <Bold />, apply: (e) => wrap(e, '**') },
    { label: 'Itálico', icon: <Italic />, apply: (e) => wrap(e, '*') },
    { label: 'Citação', icon: <Quote />, apply: (e) => prefixLines(e, '> ') },
    { label: 'Lista', icon: <List />, apply: (e) => prefixLines(e, '- ') },
];

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Campo de texto da lição com barra de formatação e prévia. O conteúdo
 * continua sendo Markdown (o mesmo que o MCP e o lesson:import gravam);
 * a barra só escreve a marcação pelo professor.
 */
export function MarkdownEditor({
    id,
    value,
    onChange,
    rows = 12,
    maxLength,
    placeholder,
    ...aria
}: Props) {
    const textarea = useRef<HTMLTextAreaElement>(null);
    const pendingSelection = useRef<[number, number] | null>(null);
    const [tab, setTab] = useState<'write' | 'preview'>('write');
    const [html, setHtml] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);

    // Depois de aplicar uma formatação, devolve o foco e a seleção ao texto.
    useEffect(() => {
        const selection = pendingSelection.current;

        if (selection && textarea.current) {
            textarea.current.focus();
            textarea.current.setSelectionRange(...selection);
            pendingSelection.current = null;
        }
    }, [value]);

    useEffect(() => {
        if (tab !== 'preview') {
            return;
        }

        const controller = new AbortController();
        setLoading(true);
        setFailed(false);

        fetch(preview.url(), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ text: value }),
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) throw new Error(String(response.status));

                return response.json() as Promise<{ html: string | null }>;
            })
            .then((data) => {
                setHtml(data.html);
                setLoading(false);
            })
            .catch((error: unknown) => {
                if (controller.signal.aborted) return;
                console.error(error);
                setFailed(true);
                setLoading(false);
            });

        return () => controller.abort();
    }, [tab, value]);

    const apply = (tool: (typeof TOOLS)[number]) => {
        const el = textarea.current;

        if (!el) return;

        const result = tool.apply({
            text: value,
            start: el.selectionStart,
            end: el.selectionEnd,
        });

        pendingSelection.current = [result.start, result.end];
        onChange(result.text);
    };

    return (
        <div className="overflow-hidden rounded-lg border bg-card shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
            <div className="flex items-center gap-1 overflow-x-auto border-b bg-muted/40 px-1.5 py-1">
                <div role="tablist" className="mr-1 flex gap-1">
                    {(
                        [
                            ['write', 'Escrever'],
                            ['preview', 'Prévia'],
                        ] as const
                    ).map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            role="tab"
                            aria-selected={tab === key}
                            onClick={() => setTab(key)}
                            className={cn(
                                'rounded-md px-2.5 py-1 text-sm font-medium text-muted-foreground',
                                tab === key &&
                                    'bg-background text-foreground shadow-xs',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>
                {tab === 'write' &&
                    TOOLS.map((tool) => (
                        <button
                            key={tool.label}
                            type="button"
                            title={tool.label}
                            aria-label={tool.label}
                            onClick={() => apply(tool)}
                            className="rounded-md p-1.5 text-muted-foreground hover:bg-background hover:text-foreground [&_svg]:size-4"
                        >
                            {tool.icon}
                        </button>
                    ))}
            </div>

            {tab === 'write' ? (
                <Textarea
                    {...aria}
                    ref={textarea}
                    id={id}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    rows={rows}
                    maxLength={maxLength}
                    placeholder={placeholder}
                    className="rounded-none border-0 font-mono text-sm shadow-none focus-visible:ring-0"
                />
            ) : (
                <div
                    className="min-h-40 overflow-y-auto px-4 py-3"
                    style={{ maxHeight: `${rows * 1.75}rem` }}
                    aria-live="polite"
                >
                    {loading ? (
                        <p className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Spinner className="size-4" /> Montando a prévia…
                        </p>
                    ) : failed ? (
                        <p className="text-sm text-destructive">
                            Não foi possível montar a prévia. Volte para
                            “Escrever” e tente de novo.
                        </p>
                    ) : html ? (
                        <RichText className="reading text-base" html={html} />
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Nada para mostrar ainda.
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
