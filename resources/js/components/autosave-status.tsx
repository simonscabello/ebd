import { AlertCircle, Check } from 'lucide-react';
import { Spinner } from '@/components/ui/spinner';
import type { AutosaveStatus as Status } from '@/hooks/use-autosave';

/** "Salvando…", "Salvo" ou o erro com "Tentar de novo" (ver useAutosave). */
export function AutosaveStatus({
    status,
    onRetry,
}: {
    status: Status;
    onRetry: () => void;
}) {
    if (status === 'saving') {
        return (
            <span className="inline-flex items-center gap-1">
                <Spinner className="size-3.5" /> Salvando…
            </span>
        );
    }

    if (status === 'saved') {
        return (
            <span className="inline-flex items-center gap-1 text-success-foreground">
                <Check className="size-3.5" /> Salvo
            </span>
        );
    }

    if (status === 'error') {
        return (
            <span className="inline-flex items-center gap-1 text-destructive">
                <AlertCircle className="size-3.5" /> Não foi possível salvar.
                <button
                    type="button"
                    onClick={onRetry}
                    className="font-medium underline underline-offset-2"
                >
                    Tentar de novo
                </button>
            </span>
        );
    }

    return null;
}
