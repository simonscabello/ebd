import { Link } from '@inertiajs/react';
import { CircleHelp, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { help } from '@/routes';

const STORAGE_KEY = 'ebd:help-hint-dismissed';

/**
 * "Primeira vez aqui?" no Painel: leva à ajuda dos professores. Some de vez
 * quando a pessoa abre a ajuda ou dispensa o aviso (neste aparelho).
 */
export function HelpHint() {
    const [dismissed, setDismissed] = useState(true);

    useEffect(() => {
        try {
            setDismissed(window.localStorage.getItem(STORAGE_KEY) === '1');
        } catch {
            setDismissed(false);
        }
    }, []);

    if (dismissed) {
        return null;
    }

    const dismiss = () => {
        setDismissed(true);

        try {
            window.localStorage.setItem(STORAGE_KEY, '1');
        } catch {
            // Sem armazenamento: o aviso volta na próxima visita.
        }
    };

    return (
        <div className="mb-6 flex items-center gap-3 rounded-2xl border border-primary/25 bg-card p-3 shadow-xs">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                <CircleHelp className="size-5" />
            </span>
            <p className="min-w-0 flex-1 text-sm leading-snug">
                <span className="font-medium">Primeira vez aqui?</span>
                <span className="block text-muted-foreground">
                    Séries, lições, domingos e chamada em poucos minutos.
                </span>
            </p>
            <Link
                href={help({ query: { para: 'professores' } })}
                onClick={dismiss}
                className="shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium text-primary hover:bg-accent"
            >
                Ver como funciona
            </Link>
            <button
                type="button"
                onClick={dismiss}
                className="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted"
                aria-label="Dispensar aviso"
            >
                <X className="size-4" />
            </button>
        </div>
    );
}
