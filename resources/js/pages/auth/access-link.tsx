import { Head, Link, router } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import { store } from '@/routes/access-link';

type Props = {
    /** Nome de quem já está logado neste aparelho (se houver). */
    currentUser: string | null;
};

/**
 * Entrada pelo link pessoal (/entrar#token). O token fica no fragmento da
 * URL, que nunca vai ao servidor no GET: ele é lido aqui, apagado da barra
 * de endereços e enviado por POST.
 */
export default function AccessLink({ currentUser }: Props) {
    const [token, setToken] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const sent = useRef(false);

    const submit = (value: string) => {
        setProcessing(true);
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

        if (!/^[A-Za-z0-9]{48}$/.test(value)) {
            setError(
                'Link incompleto. Abra de novo a mensagem do seu professor ou peça um novo link.',
            );

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
