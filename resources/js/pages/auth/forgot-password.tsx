// Components
import { Form, Head } from '@inertiajs/react';
import { LoaderCircle, MessageCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({
    status,
    emailEnabled,
}: {
    status?: string;
    emailEnabled: boolean;
}) {
    // Sem provedor de e-mail, o caminho é o link novo enviado pelo professor.
    if (!emailEnabled) {
        return (
            <>
                <Head title="Esqueci minha senha" />
                <div className="space-y-6">
                    <div className="flex gap-3 rounded-2xl border bg-card p-4 text-sm">
                        <MessageCircle className="mt-0.5 size-5 shrink-0 text-primary" />
                        <p className="text-pretty">
                            Peça ao seu professor um novo link de acesso pelo
                            WhatsApp. Ao abrir o link, você cria uma senha nova
                            e continua de onde parou.
                        </p>
                    </div>
                    <div className="space-x-1 text-center text-sm text-muted-foreground">
                        <span>Lembrou a senha?</span>
                        <TextLink href={login()}>Entrar</TextLink>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Esqueci minha senha" />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <div className="space-y-6">
                <Form {...email.form()}>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">E-mail</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="off"
                                    autoFocus
                                    placeholder="seu@email.com"
                                />

                                <InputError message={errors.email} />
                            </div>

                            <div className="my-6 flex items-center justify-start">
                                <Button
                                    className="w-full"
                                    disabled={processing}
                                    data-test="email-password-reset-link-button"
                                >
                                    {processing && (
                                        <LoaderCircle className="h-4 w-4 animate-spin" />
                                    )}
                                    Enviar link de redefinição
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <div className="space-x-1 text-center text-sm text-muted-foreground">
                    <span>Ou volte para o</span>
                    <TextLink href={login()}>login</TextLink>
                </div>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Esqueci minha senha',
    description: 'Vamos recuperar o seu acesso',
};
