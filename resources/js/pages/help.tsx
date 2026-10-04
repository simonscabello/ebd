import { Head, Link, usePage } from '@inertiajs/react';
import {
    Award,
    BellRing,
    BookOpen,
    CalendarCheck,
    CalendarDays,
    CalendarRange,
    FileText,
    KeyRound,
    LayoutDashboard,
    Layers,
    Link2,
    NotebookPen,
    Presentation,
    Send,
    Smartphone,
    TriangleAlert,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { Page, PageHeader } from '@/components/page';
import { cn } from '@/lib/utils';
import { install } from '@/routes';

type Audience = 'alunos' | 'professores';

/**
 * "Como funciona": o essencial do app para alunos e para professores, em
 * tópicos curtos. Pública, linkada no Perfil e no Painel da gestão.
 */
export default function Help() {
    const { auth } = usePage().props;
    const [tab, setTab] = useState<Audience>(
        auth.user?.can_access_admin ? 'professores' : 'alunos',
    );

    useEffect(() => {
        const param = new URLSearchParams(window.location.search).get('para');

        if (param === 'alunos' || param === 'professores') {
            setTab(param);
        }
    }, []);

    return (
        <>
            <Head title="Como funciona" />
            <Page>
                <PageHeader
                    eyebrow="Ajuda"
                    title="Como funciona"
                    description="O essencial da EBD no celular, em poucos minutos de leitura."
                />

                <div
                    className="mb-6 grid grid-cols-2 gap-1 rounded-xl bg-muted p-1"
                    role="tablist"
                    aria-label="Para quem"
                >
                    {(
                        [
                            ['alunos', 'Para alunos'],
                            ['professores', 'Para professores'],
                        ] as const
                    ).map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            role="tab"
                            id={`tab-${value}`}
                            aria-selected={tab === value}
                            aria-controls={`panel-${value}`}
                            tabIndex={tab === value ? 0 : -1}
                            onClick={() => setTab(value)}
                            onKeyDown={(event) => {
                                if (
                                    event.key === 'ArrowLeft' ||
                                    event.key === 'ArrowRight'
                                ) {
                                    event.preventDefault();
                                    const next =
                                        value === 'alunos'
                                            ? 'professores'
                                            : 'alunos';
                                    setTab(next);
                                    document
                                        .getElementById(`tab-${next}`)
                                        ?.focus();
                                }
                            }}
                            className={cn(
                                'min-h-10 rounded-lg py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                tab === value
                                    ? 'bg-background shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                <div
                    id={`panel-${tab}`}
                    role="tabpanel"
                    aria-labelledby={`tab-${tab}`}
                    className="space-y-3 pb-10"
                >
                    {tab === 'alunos' ? <ForStudents /> : <ForTeachers />}
                </div>
            </Page>
        </>
    );
}

function ForStudents() {
    return (
        <>
            <Topic icon={<Link2 />} title="Entrar com o link de acesso">
                O professor manda pelo WhatsApp um link que é só seu. Toque nele
                e pronto: você entra sem senha. Se quiser, crie uma senha em{' '}
                <strong>Perfil → Criar senha</strong> para entrar também com
                e-mail. Perdeu o acesso? Peça um novo link de acesso ao
                professor; não crie outra conta.
            </Topic>
            <Topic icon={<CalendarDays />} title="Leitura do dia">
                Uma leitura curta por dia, de segunda a sábado, preparando a
                lição de domingo. Ela aparece no <strong>Início</strong>: toque
                em <strong>Ler o texto</strong> e depois em{' '}
                <strong>Marcar como lido</strong>. Para adiantar ou pôr em dia,
                abra <strong>Leituras da semana</strong>. Alguns dias trazem uma
                curiosidade ou um conceito da lição em{' '}
                <strong>Para hoje</strong>.
            </Topic>
            <Topic icon={<NotebookPen />} title="Anotações">
                No fim de cada lição há um espaço para anotar o que Deus falou
                com você e as dúvidas para levar no domingo. Salva sozinho e só
                você vê: nem o professor tem acesso.
            </Topic>
            <Topic icon={<Presentation />} title="Modo Domingo">
                Na hora da aula, abra a lição em <strong>Modo Domingo</strong>:
                o texto base e os tópicos em letra grande, para acompanhar o
                professor. Os botões − e + ajustam o tamanho da letra.
            </Topic>
            <Topic icon={<Award />} title="Sequência e selos">
                Cada dia de leitura conta para a sua sequência. Os selos marcam
                conquistas, como a primeira semana completa ou 7 dias seguidos.
                Em <strong>Meu progresso</strong> você vê quanto falta para cada
                um.
            </Topic>
            <Topic icon={<BellRing />} title="Lembretes no celular">
                Ative em <strong>Perfil → Lembretes no celular</strong> para
                receber a leitura do dia, o aviso da véspera da EBD e as lições
                novas.
            </Topic>
            <Topic icon={<Smartphone />} title="Instalar o app">
                A EBD pode ficar na tela inicial, como um aplicativo. Veja o{' '}
                <Link
                    href={install()}
                    className="font-medium text-primary underline-offset-4 hover:underline"
                >
                    passo a passo para Android e iPhone
                </Link>
                .
            </Topic>
        </>
    );
}

function ForTeachers() {
    return (
        <>
            <Topic icon={<LayoutDashboard />} title="Estudo e gestão">
                O app tem duas partes. No <strong>estudo</strong> (Início, Minha
                semana, Biblioteca) você se prepara como qualquer aluno. O botão{' '}
                <strong>Gestão</strong>, no topo, abre a área do professor:
                painel, classes, lições e séries. Para voltar, toque em{' '}
                <strong>Voltar ao app</strong>.
            </Topic>
            <Topic icon={<Layers />} title="Séries e lições">
                A <strong>série</strong> agrupa as lições de uma revista (ex.:
                um trimestre). Crie a série em <strong>Gestão → Séries</strong>{' '}
                e depois as lições, com o número de cada uma na revista.
            </Topic>
            <Topic icon={<FileText />} title="Montar a lição">
                Toda lição nasce como <strong>rascunho</strong>, que só os
                professores veem. No editor:
                <ul className="mt-2 list-disc space-y-1 pl-5">
                    <li>
                        <strong>Estudo principal</strong>: use a barra para os
                        títulos de tópico; eles viram o roteiro do Modo Domingo.
                        Confira em <strong>Prévia</strong>.
                    </li>
                    <li>
                        <strong>Leituras</strong>: uma por dia, de segunda a
                        sábado. Aparecem no Início dos alunos, no dia de cada
                        uma.
                    </li>
                    <li>
                        <strong>Aprofundamento</strong>: roteiro, contexto,
                        curiosidades e conceitos. Escolha se é para os alunos ou{' '}
                        <strong>só professor</strong>. Em “Mostrar em Para hoje
                        no dia” você define o dia em que a curiosidade aparece
                        no Início.
                    </li>
                    <li>
                        <strong>Materiais</strong>: o PDF da revista como
                        material principal, vídeos, áudios e links.
                    </li>
                </ul>
            </Topic>
            <Topic icon={<Send />} title="Publicar">
                Quando a lição estiver pronta, toque em{' '}
                <strong>Publicar</strong>. A classe recebe um aviso no celular e
                a lição entra na biblioteca. Dá para despublicar depois.
            </Topic>
            <Topic icon={<CalendarRange />} title="Domingos">
                Na aba <strong>Domingos</strong> da classe,{' '}
                <strong>Planejar série</strong> cria um domingo por semana e
                distribui as lições pela numeração da revista. Não houve aula?
                Use <strong>Sem EBD</strong>. A lição não terminou? Use{' '}
                <strong>A lição continua no próximo</strong>.
            </Topic>
            <Topic icon={<CalendarCheck />} title="Chamada">
                A chamada abre no dia do domingo. Toque no nome de quem está
                presente e conte os visitantes; tudo salva sozinho. Esqueceu
                alguém? Dá para corrigir depois, na página do domingo. Em{' '}
                <strong>Onde paramos</strong>, anote até onde a turma chegou.
            </Topic>
            <Topic icon={<TriangleAlert />} title="Precisam de atenção">
                O Resumo da classe lista quem tem 2 faltas seguidas ou está há
                10 dias sem leitura, com um atalho para o WhatsApp. Quem entrou
                há menos de 14 dias fica de fora.
            </Topic>
            <Topic icon={<Users />} title="Alunos e link de acesso">
                Em <strong>Alunos → Adicionar aluno</strong>, cadastre com o
                nome (o WhatsApp é opcional) e envie o{' '}
                <strong>link de acesso</strong> pelo WhatsApp. Se a pessoa já
                tem conta, use <strong>Já tem conta</strong>. Na ficha do aluno
                você gera um novo link quando ele perde o acesso.
            </Topic>
            <Topic icon={<BookOpen />} title="Relatório">
                Frequência por série, por aluno e por gênero, e quem estudou em
                casa em cada lição. Tem botão para imprimir.
            </Topic>
            <Topic icon={<KeyRound />} title="Privacidade">
                As anotações pessoais dos alunos são só deles. As anotações que
                você faz na ficha de um aluno só os professores da classe veem.
            </Topic>
        </>
    );
}

function Topic({
    icon,
    title,
    children,
}: {
    icon: ReactNode;
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="flex gap-4 rounded-2xl border bg-card p-4">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground [&_svg]:size-5">
                {icon}
            </span>
            <div className="min-w-0 flex-1">
                <h2 className="font-semibold">{title}</h2>
                <div className="mt-1 text-sm leading-relaxed text-muted-foreground [&_strong]:font-medium [&_strong]:text-foreground">
                    {children}
                </div>
            </div>
        </section>
    );
}
