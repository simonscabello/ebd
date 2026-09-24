import { Link } from '@inertiajs/react';
import { Smartphone, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    canPromptInstall,
    detectPlatform,
    isStandalone,
    onInstallAvailabilityChange,
    promptInstall,
} from '@/lib/pwa';
import { install } from '@/routes';

const STORAGE_KEY = 'ebd:install-banner-dismissed-at';
const HIDE_FOR_DAYS = 30;

function dismissedRecently(): boolean {
    try {
        const at = Number(window.localStorage.getItem(STORAGE_KEY));

        return at > 0 && Date.now() - at < HIDE_FOR_DAYS * 86_400_000;
    } catch {
        return false;
    }
}

/** O aviso de instalar apareceria agora (celular, fora do app, não dispensado). */
export function installBannerWouldShow(): boolean {
    return (
        detectPlatform() !== 'other' && !isStandalone() && !dismissedRecently()
    );
}

/**
 * Aviso discreto para instalar o app no celular. Some quando o app já está
 * instalado ou quando a pessoa dispensa (volta depois de 30 dias).
 */
export function InstallAppBanner() {
    const [visible, setVisible] = useState(false);
    const [nativePrompt, setNativePrompt] = useState(false);

    useEffect(() => {
        const platform = detectPlatform();
        setVisible(
            platform !== 'other' && !isStandalone() && !dismissedRecently(),
        );
        setNativePrompt(canPromptInstall());

        return onInstallAvailabilityChange(() => {
            setNativePrompt(canPromptInstall());

            if (isStandalone()) {
                setVisible(false);
            }
        });
    }, []);

    if (!visible) {
        return null;
    }

    const dismiss = () => {
        setVisible(false);

        try {
            window.localStorage.setItem(STORAGE_KEY, String(Date.now()));
        } catch {
            // Sem armazenamento: o aviso só volta na próxima visita.
        }
    };

    return (
        <div className="mb-6 flex items-center gap-3 rounded-2xl border border-primary/25 bg-card p-3 shadow-xs">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                <Smartphone className="size-5" />
            </span>
            <p className="min-w-0 flex-1 text-sm leading-snug">
                <span className="font-medium">Instale o app</span>
                <span className="block text-muted-foreground">
                    A EBD a um toque, no seu celular.
                </span>
            </p>
            {nativePrompt ? (
                <Button
                    size="sm"
                    onClick={async () => {
                        if (await promptInstall()) {
                            setVisible(false);
                        }
                    }}
                >
                    Instalar
                </Button>
            ) : (
                <Button asChild size="sm">
                    <Link href={install()}>Ver como</Link>
                </Button>
            )}
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
