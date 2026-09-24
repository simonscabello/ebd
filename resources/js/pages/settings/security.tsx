import { Form, Head, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { SubpageHeader } from '@/components/settings/settings-list';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { account } from '@/routes';

// oxfmt-ignore
type Props = {
    passwordRules: string;
} ;

export default function Security(props: Props) {
    const hasPassword = usePage().props.auth.user?.has_password ?? true;
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Segurança" />

            <SubpageHeader
                backHref={account.url()}
                title={hasPassword ? 'Alterar senha' : 'Criar senha'}
                description="Use uma senha longa e difícil de adivinhar"
            />

            <div className="space-y-6">
                <Form
                    {...SecurityController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            {hasPassword ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="current_password">
                                        Senha atual
                                    </Label>

                                    <PasswordInput
                                        id="current_password"
                                        ref={currentPasswordInput}
                                        name="current_password"
                                        className="mt-1 block w-full"
                                        autoComplete="current-password"
                                        placeholder="Senha atual"
                                    />

                                    <InputError
                                        message={errors.current_password}
                                    />
                                </div>
                            ) : (
                                <p className="rounded-xl bg-muted/70 p-3 text-sm text-muted-foreground">
                                    Você entra pelo link pessoal. Se quiser,
                                    crie uma senha para entrar também com e-mail
                                    (cadastre o e-mail no perfil antes).
                                </p>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="password">Nova senha</Label>

                                <PasswordInput
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder="Nova senha"
                                    passwordrules={props.passwordRules}
                                />

                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirme a nova senha
                                </Label>

                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder="Repita a nova senha"
                                    passwordrules={props.passwordRules}
                                />

                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-password-button"
                                >
                                    Salvar
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
