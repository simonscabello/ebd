import { Head, usePage } from '@inertiajs/react';
import {
    CircleHelp,
    Award,
    IdCard,
    LockKeyhole,
    LogOut,
    ShieldCheck,
    Smartphone,
    Users,
} from 'lucide-react';
import AppearanceToggleTab from '@/components/appearance-tabs';
import DeleteUser from '@/components/delete-user';
import { AvatarPicker } from '@/components/settings/avatar-picker';
import { RemindersSettings } from '@/components/settings/reminders-row';
import {
    SettingsCard,
    SettingsGroup,
    SettingsRow,
} from '@/components/settings/settings-list';
import { Badge } from '@/components/ui/badge';
import { help, home, install, logout, myProgress } from '@/routes';
import { dashboard } from '@/routes/admin';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

type Props = {
    classrooms: {
        id: number;
        name: string;
        slug: string;
        role: 'teacher' | 'student';
        role_label: string;
    }[];
};

/**
 * Perfil: foto, participação nas classes, conta, segurança e aparência.
 */
export default function Account({ classrooms }: Props) {
    const { auth } = usePage().props;
    const user = auth.user!;

    return (
        <>
            <Head title="Perfil" />

            <h1 className="font-serif text-3xl font-semibold tracking-tight">
                Perfil
            </h1>

            <div className="mt-5 flex items-center gap-4">
                <AvatarPicker name={user.name} src={user.avatar_url} />
                <div className="min-w-0">
                    <p className="truncate text-xl font-semibold">
                        {user.name}
                    </p>
                    {user.email && (
                        <p className="truncate text-muted-foreground">
                            {user.email}
                        </p>
                    )}
                </div>
            </div>

            <div className="mt-8 space-y-8">
                {(classrooms.length > 0 || user.can_access_admin) && (
                    <SettingsGroup title="Minha participação">
                        <SettingsCard>
                            {user.is_member && (
                                <SettingsRow
                                    href={myProgress.url()}
                                    icon={<Award />}
                                    title="Meu progresso"
                                    description="Sequência de estudo e selos"
                                />
                            )}
                            {classrooms.map((classroom) => (
                                <SettingsRow
                                    key={classroom.id}
                                    href={home.url({
                                        query: { classe: classroom.slug },
                                    })}
                                    icon={<Users />}
                                    title={classroom.name}
                                    description="Sua classe"
                                    trailing={
                                        <Badge
                                            variant={
                                                classroom.role === 'teacher'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                            className="rounded-full"
                                        >
                                            {classroom.role_label}
                                        </Badge>
                                    }
                                />
                            ))}
                            {user.can_access_admin && (
                                <SettingsRow
                                    href={dashboard.url()}
                                    icon={<ShieldCheck />}
                                    title="Gestão da EBD"
                                    description="Classes, lições e agenda"
                                    trailing={
                                        user.is_admin ? (
                                            <Badge className="rounded-full">
                                                Admin
                                            </Badge>
                                        ) : undefined
                                    }
                                />
                            )}
                        </SettingsCard>
                    </SettingsGroup>
                )}

                <SettingsGroup title="Conta">
                    <SettingsCard>
                        <SettingsRow
                            href={editProfile.url()}
                            icon={<IdCard />}
                            title="Meus dados"
                            description="Nome e e-mail"
                        />
                    </SettingsCard>
                </SettingsGroup>

                <SettingsGroup title="Segurança">
                    <SettingsCard>
                        <SettingsRow
                            href={editSecurity.url()}
                            icon={<LockKeyhole />}
                            title={
                                user.has_password
                                    ? 'Alterar senha'
                                    : 'Criar senha'
                            }
                            description={
                                user.has_password
                                    ? 'Você precisa da senha atual'
                                    : 'Hoje você entra pelo link de acesso'
                            }
                        />
                    </SettingsCard>
                </SettingsGroup>

                <RemindersSettings />

                <SettingsGroup
                    title="Aparência"
                    description="Vale só neste aparelho."
                >
                    <AppearanceToggleTab />
                </SettingsGroup>

                <SettingsCard>
                    <SettingsRow
                        href={help.url()}
                        icon={<CircleHelp />}
                        title="Como funciona"
                        description="Ajuda rápida sobre o app"
                    />
                    <SettingsRow
                        href={install.url()}
                        icon={<Smartphone />}
                        title="Instalar o app"
                        description="A EBD na tela inicial do celular"
                    />
                    <SettingsRow
                        href={logout.url()}
                        method="post"
                        icon={<LogOut />}
                        title="Sair"
                    />
                    <DeleteUser />
                </SettingsCard>
            </div>
        </>
    );
}
