import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules?: string;
    /** Já tem conta: só os dados da igreja (POST /criar-igreja). */
    churchOnly?: boolean;
};

/**
 * "Criar minha igreja": cadastro de quem vai organizar o estudo de uma igreja
 * ou grupo. Alunos e professores não se cadastram aqui: entram pelo convite.
 */
export default function Register({ passwordRules, churchOnly = false }: Props) {
    const form = churchOnly
        ? { action: '/criar-igreja', method: 'post' as const }
        : store.form();

    return (
        <>
            <Head title="Criar minha igreja" />
            <Form
                noValidate
                {...form}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            {!churchOnly && (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Seu nome</Label>
                                        <Input
                                            id="name"
                                            type="text"
                                            required
                                            autoFocus
                                            autoComplete="name"
                                            name="name"
                                            placeholder="Seu nome completo"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">E-mail</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            required
                                            autoComplete="email"
                                            name="email"
                                            placeholder="seu@email.com"
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password">Senha</Label>
                                        <PasswordInput
                                            id="password"
                                            required
                                            autoComplete="new-password"
                                            name="password"
                                            placeholder="Crie uma senha"
                                            passwordrules={passwordRules}
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">
                                            Confirme a senha
                                        </Label>
                                        <PasswordInput
                                            id="password_confirmation"
                                            required
                                            autoComplete="new-password"
                                            name="password_confirmation"
                                            placeholder="Repita a senha"
                                            passwordrules={passwordRules}
                                        />
                                        <InputError
                                            message={
                                                errors.password_confirmation
                                            }
                                        />
                                    </div>
                                </>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="church_name">
                                    Nome da igreja ou grupo
                                </Label>
                                <Input
                                    id="church_name"
                                    type="text"
                                    required
                                    autoFocus={churchOnly}
                                    name="church_name"
                                    placeholder="Ex.: Igreja Batista Esperança"
                                />
                                <InputError message={errors.church_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="program_name">
                                    Primeiro programa de ensino
                                </Label>
                                <Input
                                    id="program_name"
                                    type="text"
                                    name="program_name"
                                    defaultValue="Escola Bíblica Dominical"
                                />
                                <p className="text-sm text-muted-foreground">
                                    Ex.: Escola Bíblica Dominical, Discipulado,
                                    Pequeno grupo. Dá para ter outros depois.
                                </p>
                                <InputError message={errors.program_name} />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Criar minha igreja
                            </Button>
                        </div>

                        {!churchOnly && (
                            <div className="text-center text-sm text-muted-foreground">
                                Já tem conta?{' '}
                                <TextLink href={login()}>Entrar</TextLink>
                            </div>
                        )}
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Criar minha igreja',
    description:
        'É gratuito. Você organiza as turmas, convida professores e alunos e acompanha o estudo. É aluno? Peça o link de acesso ao seu professor.',
};
