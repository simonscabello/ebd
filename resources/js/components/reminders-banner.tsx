import { BellRing, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { installBannerWouldShow } from '@/components/install-app-banner';
import { Button } from '@/components/ui/button';
import { usePushReminders } from '@/hooks/use-push-reminders';

const STORAGE_KEY = 'ebd:reminders-banner-dismissed-at';
const HIDE_FOR_DAYS = 14;

function dismissedRecently(): boolean {
    try {
        const at = Number(window.localStorage.getItem(STORAGE_KEY));

        return at > 0 && Date.now() - at < HIDE_FOR_DAYS * 86_400_000;
    } catch {
        return false;
    }
}

/**
 * Convite para ativar os lembretes (leitura do dia, véspera da EBD e lição
 * nova). Só aparece quando o aparelho suporta push e ainda não está inscrito;
 * cede a vez ao aviso de instalar o app.
 */
export function RemindersBanner() {
    const { status, busy, enable } = usePushReminders();
    const [dismissed, setDismissed] = useState(true);

    useEffect(() => {
        setDismissed(dismissedRecently() || installBannerWouldShow());
    }, []);

    if (dismissed || status !== 'off') {
        return null;
    }

    const dismiss = () => {
        setDismissed(true);

        try {
            window.localStorage.setItem(STORAGE_KEY, String(Date.now()));
        } catch {
            // Sem armazenamento: o aviso só volta na próxima visita.
        }
    };

    return (
        <div className="mb-6 flex items-center gap-3 rounded-2xl border border-primary/25 bg-card p-3 shadow-xs">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                <BellRing className="size-5" />
            </span>
            <p className="min-w-0 flex-1 text-sm leading-snug">
                <span className="font-medium">Quer um lembrete?</span>
                <span className="block text-muted-foreground">
                    A leitura do dia e a lição nova, no seu celular.
                </span>
            </p>
            <Button size="sm" onClick={enable} disabled={busy}>
                Ativar
            </Button>
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
