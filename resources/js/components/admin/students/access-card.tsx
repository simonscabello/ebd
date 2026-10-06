import { router } from '@inertiajs/react';
import {
    BellOff,
    BellRing,
    CircleCheck,
    KeyRound,
    Link2,
    ShieldOff,
    UserRoundPen,
} from 'lucide-react';
import type { IssuedLink } from '@/components/admin/access-link-dialog';
import { useConfirm } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { relativeDay } from '@/lib/dates';
import {
    destroy as revokeLink,
    store as issueLink,
} from '@/routes/admin/classrooms/members/link';
import type { Classroom } from '@/types';

export type StudentAccess = {
    link: {
        use_count: number;
        last_used_at: string | null;
        created_at: string;
    } | null;
    devices: number;
};

/**
 * Como o aluno entra no app: cadastro, link pessoal e lembretes no celular.
 */
export function AccessCard({
    classroom,
    student,
    access,
    today,
    onIssued,
}: {
    classroom: Classroom;
    student: {
        id: number;
        name: string;
        has_password: boolean;
        profile_complete: boolean;
    };
    access: StudentAccess;
    today: string;
    onIssued: (link: IssuedLink) => void;
}) {
    const confirm = useConfirm();
    const target = { classroom: classroom.slug, user: student.id };

    const generate = async () => {
        const ok = await confirm(
            student.has_password
                ? {
                      title: `Gerar um novo link de acesso para ${student.name}?`,
                      description:
                          'Use quando a pessoa esqueceu a senha. Ao abrir o link, a senha atual deixa de valer e ela cria outra.',
                      confirmLabel: 'Gerar link',
                  }
                : {
                      title: `Gerar ${access.link ? 'um novo link de acesso' : 'o link de acesso'} para ${student.name}?`,
                      description: access.link
                          ? 'O link atual deixa de funcionar.'
                          : 'Você envia o link pelo WhatsApp; a pessoa entra e completa o cadastro.',
                      confirmLabel: 'Gerar link',
                  },
        );

        if (ok) {
            router.post(
                issueLink.url(target),
                {},
                {
                    preserveScroll: true,
                    onFlash: (flash) => {
                        if (flash.accessLink) {
                            onIssued(flash.accessLink as IssuedLink);
                        }
                    },
                },
            );
        }
    };

    const block = async () => {
        if (
            await confirm({
                title: `Bloquear o acesso de ${student.name}?`,
                description:
                    'Todos os aparelhos conectados saem da conta. Para voltar, gere um novo link de acesso.',
                confirmLabel: 'Bloquear',
                destructive: true,
            })
        ) {
            router.delete(revokeLink.url(target), { preserveScroll: true });
        }
    };

    const lastDay = access.link?.last_used_at?.slice(0, 10);

    return (
        <div className="rounded-2xl border bg-card p-4 text-sm">
            <ul className="space-y-2">
                <li className="flex items-center gap-2">
                    {student.profile_complete ? (
                        <>
                            <CircleCheck className="size-4 text-success-foreground" />
                            Cadastro completo: entra com e-mail e senha
                        </>
                    ) : (
                        <>
                            <UserRoundPen className="size-4 text-warning-foreground" />
                            <span className="font-medium text-warning-foreground">
                                Cadastro pendente
                            </span>
                            <span className="text-muted-foreground">
                                · completa no próximo acesso pelo link
                            </span>
                        </>
                    )}
                </li>
                <li className="flex items-center gap-2 text-muted-foreground">
                    <KeyRound className="size-4" />
                    {access.link
                        ? `Link ativo · usado ${access.link.use_count}x${lastDay ? ` · último acesso ${relativeDay(lastDay, today)}` : ''}`
                        : 'Sem link ativo'}
                </li>
                <li className="flex items-center gap-2 text-muted-foreground">
                    {access.devices > 0 ? (
                        <>
                            <BellRing className="size-4 text-success-foreground" />
                            Lembretes ativos em {access.devices}{' '}
                            {access.devices === 1 ? 'aparelho' : 'aparelhos'}
                        </>
                    ) : (
                        <>
                            <BellOff className="size-4" /> Sem lembretes no
                            celular
                        </>
                    )}
                </li>
            </ul>
            <div className="mt-4 flex flex-wrap gap-2">
                <Button size="sm" variant="outline" onClick={generate}>
                    <Link2 />
                    {student.has_password
                        ? 'Novo link de acesso'
                        : access.link
                          ? 'Novo link de acesso'
                          : 'Gerar link de acesso'}
                </Button>
                {access.link && (
                    <Button size="sm" variant="outline" onClick={block}>
                        <ShieldOff /> Bloquear acesso
                    </Button>
                )}
            </div>
        </div>
    );
}
