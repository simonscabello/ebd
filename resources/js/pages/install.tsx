import { Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    Download,
    EllipsisVertical,
    KeyRound,
    Share,
    SquarePlus,
    Smartphone,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import {
    canPromptInstall,
    detectPlatform,
    isStandalone,
    onInstallAvailabilityChange,
    promptInstall,
} from '@/lib/pwa';
import type { Platform } from '@/lib/pwa';
import { cn } from '@/lib/utils';
import { show as accessLink } from '@/routes/access-link';

/**
 * Tutorial para instalar o app (PWA) no Android e no iPhone.
 */
export default function Install() {
    const [tab, setTab] = useState<'android' | 'ios'>('android');
    const [installed, setInstalled] = useState(false);
    const [nativePrompt, setNativePrompt] = useState(false);

    useEffect(() => {
        const platform: Platform = detectPlatform();
        setTab(platform === 'ios' ? 'ios' : 'android');
        setInstalled(isStandalone());
        setNativePrompt(canPromptInstall());

        return onInstallAvailabilityChange(() =>
            setNativePrompt(canPromptInstall()),
        );
    }, []);

    return (
        <>
            <Head title="Instalar o app" />
            <Page>
                <PageHeader
                    eyebrow="EBD no seu celular"
                    title="Instalar o app"
                    description="Leva menos de um minuto e não precisa de loja de aplicativos. O ícone da EBD fica na tela inicial, junto dos seus apps."
                />

                {installed && (
                    <p className="mb-6 flex items-center gap-2 rounded-2xl border border-success/50 bg-success-soft p-4 text-success-foreground">
                        <CheckCircle2 className="size-5 shrink-0" /> Você já
                        está usando o app. Tudo certo!
                    </p>
                )}

                <div
                    className="mb-6 grid grid-cols-2 gap-1 rounded-xl bg-muted p-1"
                    role="tablist"
                    aria-label="Sistema do celular"
                >
                    {(
                        [
                            ['android', 'Android'],
                            ['ios', 'iPhone'],
                        ] as const
                    ).map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            role="tab"
                            id={`tab-${value}`}
                            aria-selected={tab === value}
                            aria-controls={`panel-${value}`}
                            tabIndex={tab === value ? 0 : -1}
                            onClick={() => setTab(value)}
                            onKeyDown={(event) => {
                                if (
                                    event.key === 'ArrowLeft' ||
                                    event.key === 'ArrowRight'
                                ) {
                                    event.preventDefault();
                                    const next =
                                        value === 'android' ? 'ios' : 'android';
                                    setTab(next);
                                    document
                                        .getElementById(`tab-${next}`)
                                        ?.focus();
                                }
                            }}
                            className={cn(
                                'min-h-10 rounded-lg py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                tab === value
                                    ? 'bg-background shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {tab === 'android' ? (
                    <div
                        id="panel-android"
                        role="tabpanel"
                        aria-labelledby="tab-android"
                        className="space-y-4"
                    >
                        {nativePrompt && (
                            <Button
                                size="lg"
                                className="w-full"
                                onClick={() => void promptInstall()}
                            >
                                <Download /> Instalar agora
                            </Button>
                        )}
                        <Steps>
                            <Step n={1} icon={<Smartphone />}>
                                Abra este site no <strong>Google Chrome</strong>
                                .
                            </Step>
                            <Step n={2} icon={<EllipsisVertical />}>
                                Toque nos <strong>três pontinhos</strong> (⋮),
                                no canto de cima, à direita.
                            </Step>
                            <Step n={3} icon={<Download />}>
                                Toque em <strong>Instalar app</strong> (em
                                alguns celulares aparece{' '}
                                <strong>Adicionar à tela inicial</strong>).
                            </Step>
                            <Step n={4} icon={<CheckCircle2 />}>
                                Confirme em <strong>Instalar</strong>. Pronto:
                                abra pelo ícone da EBD na tela inicial.
                            </Step>
                        </Steps>
                        <p className="text-sm text-muted-foreground">
                            No Android o app usa o mesmo login do Chrome: se
                            você já entrou pelo link do professor, continua
                            conectado.
                        </p>
                    </div>
                ) : (
                    <div
                        id="panel-ios"
                        role="tabpanel"
                        aria-labelledby="tab-ios"
                        className="space-y-4"
                    >
                        <Steps>
                            <Step n={1} icon={<Smartphone />}>
                                Abra este site no <strong>Safari</strong> (o
                                navegador da bússola azul).
                            </Step>
                            <Step n={2} icon={<Share />}>
                                Toque em <strong>Compartilhar</strong>: o
                                quadrado com a seta para cima, na barra de
                                baixo.
                            </Step>
                            <Step n={3} icon={<SquarePlus />}>
                                Role a lista e toque em{' '}
                                <strong>Adicionar à Tela de Início</strong>.
                                Depois toque em <strong>Adicionar</strong>.
                            </Step>
                            <Step n={4} icon={<CheckCircle2 />}>
                                Abra pelo novo ícone da EBD na tela inicial.
                            </Step>
                            <Step n={5} icon={<KeyRound />}>
                                O iPhone não leva o login do Safari para o app.
                                Se pedir para entrar,{' '}
                                <strong>copie o link pessoal</strong> que o
                                professor mandou no WhatsApp (toque e segure no
                                link → Copiar) e cole em{' '}
                                <Link
                                    href={accessLink()}
                                    className="font-medium text-primary underline"
                                >
                                    Entrar com meu link
                                </Link>
                                .
                            </Step>
                        </Steps>
                    </div>
                )}
            </Page>
        </>
    );
}

function Steps({ children }: { children: ReactNode }) {
    return <ol className="space-y-3">{children}</ol>;
}

function Step({
    n,
    icon,
    children,
}: {
    n: number;
    icon: ReactNode;
    children: ReactNode;
}) {
    return (
        <li className="flex gap-4 rounded-2xl border bg-card p-4">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                {n}
            </span>
            <div className="flex-1 leading-relaxed">
                <span className="mb-1 block text-primary [&_svg]:size-5">
                    {icon}
                </span>
                {children}
            </div>
        </li>
    );
}
