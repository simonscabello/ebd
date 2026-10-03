import { Head, Link } from '@inertiajs/react';
import { BookOpen, Home } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AuthSimpleLayout from '@/layouts/auth/auth-simple-layout';
import { home, library } from '@/routes';

const MESSAGES: Record<number, { title: string; description: string }> = {
    403: {
        title: 'Esta página não é para você',
        description:
            'Sua conta não tem acesso a este conteúdo. Se acha que deveria ter, fale com o seu professor.',
    },
    404: {
        title: 'Não encontramos esta página',
        description:
            'O link pode estar errado ou a lição pode ter saído do ar. Procure a lição na biblioteca.',
    },
    500: {
        title: 'Algo deu errado do nosso lado',
        description:
            'Não foi culpa sua. Tente de novo em alguns minutos; se continuar, avise o seu professor.',
    },
    503: {
        title: 'Voltamos já',
        description:
            'O app está passando por uma atualização rápida. Tente de novo em alguns minutos.',
    },
};

/**
 * Página de erro (403, 404, 500, 503), renderizada por bootstrap/app.php
 * quando o debug está desligado.
 */
export default function ErrorPage({ status }: { status: number }) {
    const message = MESSAGES[status] ?? MESSAGES[500];

    return (
        <>
            <Head title={message.title} />
            <AuthSimpleLayout
                title={message.title}
                description={message.description}
            >
                <div className="flex flex-col gap-3">
                    <Button asChild size="lg">
                        <Link href={home()}>
                            <Home /> Ir para o início
                        </Link>
                    </Button>
                    <Button asChild size="lg" variant="outline">
                        <Link href={library()}>
                            <BookOpen /> Ver biblioteca
                        </Link>
                    </Button>
                    <p className="text-center text-xs text-muted-foreground">
                        Erro {status}
                    </p>
                </div>
            </AuthSimpleLayout>
        </>
    );
}
