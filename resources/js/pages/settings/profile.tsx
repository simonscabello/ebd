import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { SubpageHeader } from '@/components/settings/settings-list';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { account } from '@/routes';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile() {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Meus dados" />

            <SubpageHeader
                backHref={account.url()}
                title="Meus dados"
                description="Seu nome e e-mail"
            />

            <div className="space-y-6">
                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nome</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user?.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Seu nome completo"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">E-mail</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user?.email ?? ''}
                                    name="email"
                                    required={auth.user?.has_password}
                                    autoComplete="username"
                                    placeholder="seu@email.com"
                                />

                                {!auth.user?.has_password && (
                                    <p className="text-xs text-muted-foreground">
                                        Opcional. Você entra pelo link pessoal
                                        enviado pelo professor. Cadastre um
                                        e-mail se quiser criar uma senha.
                                    </p>
                                )}
                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
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
