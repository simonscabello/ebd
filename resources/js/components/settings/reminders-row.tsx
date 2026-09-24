import { Link, router } from '@inertiajs/react';
import { BellOff, BellRing, Send } from 'lucide-react';
import { useState } from 'react';
import {
    SettingsCard,
    SettingsGroup,
} from '@/components/settings/settings-list';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { usePushReminders } from '@/hooks/use-push-reminders';
import { detectPlatform, isStandalone } from '@/lib/pwa';
import { install } from '@/routes';
import { test as testPush } from '@/routes/push';

/**
 * Lembretes no Perfil: estado neste aparelho e o botão de ativar/desativar.
 */
export function RemindersSettings() {
    const { status, busy, enable, disable } = usePushReminders();
    const [testing, setTesting] = useState(false);

    const sendTest = () => {
        router.post(testPush.url(), undefined, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setTesting(true),
            onFinish: () => setTesting(false),
        });
    };

    const description = (() => {
        switch (status) {
            case 'checking':
                return 'Verificando este aparelho…';
            case 'unsupported':
                return detectPlatform() === 'ios' && !isStandalone() ? (
                    <>
                        No iPhone, primeiro{' '}
                        <Link href={install()} className="underline">
                            instale o app
                        </Link>{' '}
                        na tela inicial.
                    </>
                ) : (
                    'Este navegador não recebe notificações.'
                );
            case 'denied':
                return 'Bloqueado neste navegador. Libere as notificações do site nas configurações dele.';
            case 'on':
                return 'Leitura do dia (9h e 20h), véspera da EBD e lição nova.';
            case 'off':
                return 'Leitura do dia, véspera da EBD e lição nova, neste aparelho.';
        }
    })();

    return (
        <SettingsGroup
            title="Notificações"
            description="Vale só neste aparelho."
        >
            <SettingsCard>
                <div className="flex w-full items-center gap-3.5 px-4 py-3.5">
                    <span className="shrink-0 text-muted-foreground [&_svg]:size-5">
                        {status === 'on' ? <BellRing /> : <BellOff />}
                    </span>
                    <span className="min-w-0 flex-1">
                        <span className="block font-medium">
                            Lembretes no celular
                        </span>
                        <span className="block text-sm text-muted-foreground">
                            {description}
                        </span>
                    </span>
                    {status === 'on' && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={disable}
                            disabled={busy}
                        >
                            {busy ? <Spinner /> : null} Desativar
                        </Button>
                    )}
                    {status === 'off' && (
                        <Button size="sm" onClick={enable} disabled={busy}>
                            {busy ? <Spinner /> : null} Ativar
                        </Button>
                    )}
                </div>
                {status === 'on' && (
                    <button
                        type="button"
                        onClick={sendTest}
                        disabled={testing}
                        className="flex w-full items-center gap-3.5 px-4 py-3.5 text-left transition-colors hover:bg-muted/50 disabled:opacity-60"
                    >
                        <span className="shrink-0 text-muted-foreground [&_svg]:size-5">
                            {testing ? <Spinner /> : <Send />}
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block font-medium">
                                Enviar uma notificação de teste
                            </span>
                            <span className="block text-sm text-muted-foreground">
                                Só para os seus aparelhos. Deve chegar em
                                segundos.
                            </span>
                        </span>
                    </button>
                )}
            </SettingsCard>
        </SettingsGroup>
    );
}
