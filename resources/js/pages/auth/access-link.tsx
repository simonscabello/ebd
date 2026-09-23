import { Head, Link, router } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import { store } from '@/routes/access-link';

type Props = {
    /** Nome de quem já está logado neste aparelho (se houver). */
    currentUser: string | null;
};

const TOKEN = /^[A-Za-z0-9]{48}$/;

/**
 * Extrai o token de um link colado (…/entrar#TOKEN) ou do próprio token.
 */
function tokenFrom(text: string): string | null {
    const value = text.trim();
    const candidate = value.includes('#')
        ? (value.split('#').pop() ?? '')
        : value;

    return TOKEN.test(candidate) ? candidate : null;
}

/**
 * Entrada pelo link pessoal (/entrar#token). O token fica no fragmento da
 * URL, que nunca vai ao servidor no GET: ele é lido aqui, apagado da barra
 * de endereços e enviado por POST.
 *
 * Sem token no endereço (ex.: app instalado no iPhone, que não compartilha o
 * login com o Safari), a pessoa pode colar o link recebido no WhatsApp.
 */
export default function AccessLink({ currentUser }: Props) {
    const [token, setToken] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [pasteMode, setPasteMode] = useState(false);
    const [pasted, setPasted] = useState('');
    const sent = useRef(false);

    const submit = (value: string) => {
        setProcessing(true);
        setError(null);
        router.post(
            store.url(),
            { token: value },
            {
                onError: (errors) => {
                    setError(
                        errors.token ??
                            'Não foi possível entrar. Peça um novo link ao seu professor.',
                    );
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    useEffect(() => {
        const value = window.location.hash.replace(/^#/, '');

        // Remove o token da barra de endereços e do histórico.
        window.history.replaceState(
            null,
            '',
            window.location.pathname + window.location.search,
        );

        if (!TOKEN.test(value)) {
            setPasteMode(true);

            return;
        }

        setToken(value);

        // Com outra pessoa logada neste aparelho, pede confirmação antes de trocar.
        if (!currentUser && !sent.current) {
            sent.current = true;
            submit(value);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    if (pasteMode) {
        return (
            <>
                <Head title="Entrar com meu link" />
                <form
                    className="flex flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const value = tokenFrom(pasted);

                        if (!value) {
                            setError(
                                'Este não parece ser o link da EBD. Copie a mensagem do professor de novo e cole aqui.',
                            );

                            return;
                        }

                        submit(value);
                    }}
                >
                    <div className="flex flex-col items-center gap-2 text-center">
                        <span className="flex size-12 items-center justify-center rounded-full bg-accent text-accent-foreground">
                            <KeyRound className="size-6" />
                        </span>
                        <p className="text-balance text-muted-foreground">
                            No WhatsApp, toque e segure no link que o professor
                            enviou, escolha <strong>Copiar</strong> e cole aqui.
                        </p>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="access-link">Seu link pessoal</Label>
                        <Input
                            id="access-link"
                            value={pasted}
                            onChange={(event) => setPasted(event.target.value)}
                            placeholder="https://…/entrar#…"
                            autoComplete="off"
                            autoCapitalize="off"
                            spellCheck={false}
                            required
                        />
                        <InputError message={error ?? undefined} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />} Entrar
                    </Button>
                    <Button asChild variant="ghost">
                        <Link href={home()}>Voltar ao início</Link>
                    </Button>
                </form>
            </>
        );
    }

    return (
        <>
            <Head title="Entrar com seu link" />

            <div className="flex flex-col items-center gap-4 text-center">
                <span className="flex size-12 items-center justify-center rounded-full bg-accent text-accent-foreground">
                    <KeyRound className="size-6" />
                </span>

                {error ? (
                    <>
                        <p className="text-balance">{error}</p>
                        <Button asChild variant="outline">
                            <Link href={home()}>Ir para o início</Link>
                        </Button>
                    </>
                ) : currentUser && token && !processing ? (
                    <>
                        <p className="text-balance">
                            Este aparelho está conectado como{' '}
                            <strong>{currentUser}</strong>. Entrar com o novo
                            link vai trocar de pessoa.
                        </p>
                        <div className="flex gap-2">
                            <Button onClick={() => submit(token)}>
                                Trocar e entrar
                            </Button>
                            <Button asChild variant="ghost">
                                <Link href={home()}>Cancelar</Link>
                            </Button>
                        </div>
                    </>
                ) : (
                    <p className="flex items-center gap-2 text-muted-foreground">
                        <Spinner /> Entrando…
                    </p>
                )}
            </div>
        </>
    );
}
