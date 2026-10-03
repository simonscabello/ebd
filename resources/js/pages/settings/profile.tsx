import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import type { GenderOption } from '@/components/gender-field';
import { GenderField } from '@/components/gender-field';
import { SubpageHeader } from '@/components/settings/settings-list';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { todayOnDevice } from '@/lib/dates';
import { formatPhone } from '@/lib/phone';
import { account } from '@/routes';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

type Props = {
    profile: {
        phone: string | null;
        birth_date: string | null;
        gender: string | null;
    };
    /** Aluno: WhatsApp, nascimento e gênero são obrigatórios. */
    isStudent: boolean;
    genders: GenderOption[];
};

export default function Profile({ profile, isStudent, genders }: Props) {
    const { auth } = usePage<PageProps>().props;
    const today = todayOnDevice();

    return (
        <>
            <Head title="Meus dados" />

            <SubpageHeader
                backHref={account.url()}
                title="Meus dados"
                description="Seu nome, contato e aniversário"
            />

            <div className="space-y-6">
                <Form
                    noValidate
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
                                        Opcional. Você entra pelo link de acesso
                                        enviado pelo professor. Cadastre um
                                        e-mail se quiser criar uma senha.
                                    </p>
                                )}
                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">WhatsApp</Label>

                                <Input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    inputMode="tel"
                                    className="mt-1 block w-full"
                                    defaultValue={formatPhone(profile.phone)}
                                    required={isStudent}
                                    autoComplete="tel"
                                    placeholder="(27) 99999-0000"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.phone}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="birth_date">
                                    Data de nascimento
                                </Label>

                                <Input
                                    id="birth_date"
                                    name="birth_date"
                                    type="date"
                                    className="mt-1 block w-full"
                                    defaultValue={profile.birth_date ?? ''}
                                    required={isStudent}
                                    autoComplete="bday"
                                    min="1900-01-01"
                                    max={today}
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.birth_date}
                                />
                            </div>

                            <GenderField
                                options={genders}
                                defaultValue={profile.gender}
                                required={isStudent}
                                error={errors.gender}
                            />

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
