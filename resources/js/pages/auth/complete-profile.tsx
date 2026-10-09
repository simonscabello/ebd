import { Form, Head } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import type { GenderOption } from '@/components/gender-field';
import { GenderField } from '@/components/gender-field';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { todayOnDevice } from '@/lib/dates';
import { formatPhone } from '@/lib/phone';
import { store } from '@/routes/onboarding';

type Props = {
    profile: {
        name: string;
        email: string | null;
        phone: string | null;
        birth_date: string | null;
        gender: string | null;
    };
    needsPassword: boolean;
    passwordRules: string;
    genders: GenderOption[];
};

export default function CompleteProfile({
    profile,
    needsPassword,
    passwordRules,
    genders,
}: Props) {
    const today = todayOnDevice();

    return (
        <>
            <Head title="Complete seu cadastro" />
            <Form
                noValidate
                {...store.form()}
                resetOnError={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nome</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    autoComplete="name"
                                    defaultValue={profile.name}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">WhatsApp</Label>
                                <Input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    inputMode="tel"
                                    required
                                    autoComplete="tel"
                                    placeholder="(27) 99999-0000"
                                    defaultValue={formatPhone(profile.phone)}
                                    aria-describedby="phone-hint"
                                />
                                <p
                                    id="phone-hint"
                                    className="text-xs text-muted-foreground"
                                >
                                    Com DDD. É por ele que o professor fala com
                                    você.
                                </p>
                                <InputError message={errors.phone} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="birth_date">
                                    Data de nascimento
                                </Label>
                                <Input
                                    id="birth_date"
                                    name="birth_date"
                                    type="date"
                                    required
                                    autoComplete="bday"
                                    min="1900-01-01"
                                    max={today}
                                    defaultValue={profile.birth_date ?? ''}
                                />
                                <InputError message={errors.birth_date} />
                            </div>

                            <GenderField
                                options={genders}
                                defaultValue={profile.gender}
                                required
                                error={errors.gender}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="email">E-mail</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    autoComplete="email"
                                    placeholder="seu@email.com"
                                    defaultValue={profile.email ?? ''}
                                    aria-describedby="email-hint"
                                />
                                <p
                                    id="email-hint"
                                    className="text-xs text-muted-foreground"
                                >
                                    Você vai usar para entrar no app.
                                </p>
                                <InputError message={errors.email} />
                            </div>

                            {needsPassword && (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            Crie uma senha
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            required
                                            autoComplete="new-password"
                                            passwordrules={passwordRules}
                                            aria-describedby="password-hint"
                                        />
                                        <p
                                            id="password-hint"
                                            className="text-xs text-muted-foreground"
                                        >
                                            Pelo menos 6 caracteres, com letras
                                            e números.
                                        </p>
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">
                                            Repita a senha
                                        </Label>
                                        <PasswordInput
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            required
                                            autoComplete="new-password"
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

                            <Button type="submit" className="mt-2 w-full">
                                {processing && <Spinner />}
                                Salvar e continuar
                            </Button>
                        </div>

                        <p className="flex gap-2 text-xs text-muted-foreground">
                            <Lock className="mt-0.5 size-3.5 shrink-0" />
                            Seus dados ficam só com os professores da sua
                            classe: servem para os aniversários e os relatórios
                            da EBD.
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

CompleteProfile.layout = {
    title: 'Complete seu cadastro',
    description:
        'Leva um minuto. Depois disso você entra com e‑mail e senha, em qualquer aparelho.',
};
