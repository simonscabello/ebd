import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Award,
    BellRing,
    BookOpen,
    CalendarDays,
    ClipboardCheck,
    Headphones,
    Heart,
    MessageCircleQuestion,
    Podcast,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { CopyField } from '@/components/site/copy-field';
import { Button } from '@/components/ui/button';
import { brand } from '@/lib/brand';
import { library, login, register, support } from '@/routes';
import { show } from '@/routes/lessons';
import type { Lesson } from '@/types';

type Props = {
    /** Estudos publicados da biblioteca oficial (pode vir vazio). */
    examples: Lesson[];
    pixKey: string;
};

/** Capturas do app com os dados de exemplo (public/site, geradas com Playwright). */
const screenshots = [
    {
        src: '/site/aluno-semana.jpg',
        alt: 'Tela inicial do aluno com a lição atual, a série e a capa da lição',
        caption: 'O Início do aluno: a lição da vez, com a capa e a série.',
    },
    {
        src: '/site/estudo.jpg',
        alt: 'Página de uma lição com a capa, o texto base e o estudo',
        caption: 'O estudo da lição, com o texto bíblico a um toque.',
    },
    {
        src: '/site/chamada.jpg',
        alt: 'Gestão da classe no celular do professor, com a lição atual e o botão de chamada',
        caption: 'O professor acompanha a classe e faz a chamada no celular.',
    },
] as const;

const benefits = [
    {
        icon: BookOpen,
        title: 'O aluno estuda a semana toda',
        text: 'Um pouco por dia, com leituras curtas e o texto bíblico na mão. Ele chega ao domingo sabendo a lição.',
    },
    {
        icon: Users,
        title: 'O professor acompanha a turma',
        text: 'Vê quem está lendo, quem faltou e quem precisa de um incentivo, sem planilha.',
    },
    {
        icon: ClipboardCheck,
        title: 'Chamada no celular',
        text: 'Presença marcada em segundos durante a aula, com o histórico de cada aluno guardado.',
    },
] as const;

const extras = [
    {
        icon: CalendarDays,
        title: 'Minha semana',
        text: 'A leitura de cada dia até a aula, para adiantar ou pôr em dia.',
    },
    {
        icon: Headphones,
        title: 'Estudo em áudio',
        text: 'A lição narrada para ouvir no ônibus, no carro ou na cozinha.',
    },
    {
        icon: Podcast,
        title: 'Podcast da igreja',
        text: 'Os áudios das lições num podcast próprio, que dá para pôr no Spotify.',
    },
    {
        icon: MessageCircleQuestion,
        title: 'Tirar dúvida',
        text: 'O aluno pergunta sobre o texto na própria lição. O professor vê as dúvidas da turma, sem nomes.',
    },
    {
        icon: Award,
        title: 'Sequência e selos',
        text: 'Dias seguidos de estudo e selos que incentivam sem expor ninguém.',
    },
    {
        icon: BellRing,
        title: 'Lembretes no celular',
        text: 'A leitura do dia e a lição nova chegam como notificação.',
    },
] as const;

const steps = [
    {
        title: 'Crie a sua igreja',
        text: 'Um cadastro rápido, sem custo. Depois crie as classes com o dia e o horário de cada uma.',
    },
    {
        title: 'Cadastre a lição',
        text: 'Escreva a lição da semana ou parta de um estudo pronto da biblioteca.',
    },
    {
        title: 'Convide a classe',
        text: 'Mande o link pelo WhatsApp. O aluno entra pelo celular, sem instalar nada.',
    },
    {
        title: 'Dê a aula',
        text: 'No dia do encontro, conduza o estudo e faça a chamada pelo celular.',
    },
] as const;

/**
 * Site de apresentação: o que vê quem abre o endereço sem ter entrado.
 */
export default function SiteHome({ examples, pixKey }: Props) {
    const { features } = usePage().props;

    return (
        <>
            <Head title={`${brand.name} · ${brand.tagline}`} />

            {/* Abertura */}
            <section className="mx-auto grid max-w-6xl items-center gap-10 px-4 pt-10 pb-14 sm:px-6 md:grid-cols-[1.15fr_1fr] md:pt-16 md:pb-20">
                <div>
                    <p className="text-sm font-medium text-primary">
                        Gratuito para igrejas
                    </p>
                    <h1 className="mt-3 font-serif text-4xl leading-tight font-semibold tracking-tight text-balance sm:text-5xl">
                        {brand.tagline}
                    </h1>
                    <p className="mt-5 max-w-xl text-lg text-pretty text-muted-foreground">
                        A {brand.name} leva a Escola Bíblica para o celular da
                        classe: o aluno estuda um pouco por dia, o professor
                        acompanha a turma e faz a chamada na hora da aula.
                    </p>
                    <CallToAction registration={features.registration} />
                </div>
                <PhoneShot
                    src={screenshots[0].src}
                    alt={screenshots[0].alt}
                    className="mx-auto w-full max-w-[17rem]"
                    priority
                />
            </section>

            {/* Benefícios */}
            <section className="border-y border-border/70 bg-card">
                <div className="mx-auto grid max-w-6xl gap-8 px-4 py-14 sm:px-6 md:grid-cols-3">
                    {benefits.map(({ icon: Icon, title, text }) => (
                        <div key={title}>
                            <span className="inline-flex size-11 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                                <Icon className="size-5" aria-hidden />
                            </span>
                            <h2 className="mt-4 font-serif text-xl font-semibold">
                                {title}
                            </h2>
                            <p className="mt-2 text-muted-foreground">{text}</p>
                        </div>
                    ))}
                </div>
            </section>

            {/* Capturas */}
            <Section
                title="O app funcionando"
                description="Telas reais, com dados de exemplo."
            >
                <ul className="grid gap-8 sm:grid-cols-3">
                    {screenshots.map((shot, index) => (
                        <li
                            key={shot.src}
                            // No celular a primeira já aparece na abertura.
                            className={
                                index === 0
                                    ? 'hidden space-y-3 sm:block'
                                    : 'space-y-3'
                            }
                        >
                            <PhoneShot
                                src={shot.src}
                                alt={shot.alt}
                                className="mx-auto w-full max-w-[16rem]"
                            />
                            <p className="mx-auto max-w-[16rem] text-center text-sm text-muted-foreground">
                                {shot.caption}
                            </p>
                        </li>
                    ))}
                </ul>
            </Section>

            {/* Recursos */}
            <Section
                title="E tem mais"
                description="O que a classe ganhou nos últimos meses."
                className="bg-card"
            >
                <ul className="grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                    {extras.map(({ icon: Icon, title, text }) => (
                        <li key={title} className="flex gap-4">
                            <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                                <Icon className="size-5" aria-hidden />
                            </span>
                            <div>
                                <h3 className="font-semibold">{title}</h3>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {text}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            </Section>

            {/* Como funciona */}
            <Section
                title="Como funciona"
                description="Do cadastro à primeira aula em poucos passos."
            >
                <ol className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {steps.map((step, index) => (
                        <li
                            key={step.title}
                            className="rounded-2xl border border-border bg-card p-5"
                        >
                            <span className="inline-flex size-8 items-center justify-center rounded-full bg-primary font-serif text-sm font-semibold text-primary-foreground">
                                {index + 1}
                            </span>
                            <h3 className="mt-4 font-semibold">{step.title}</h3>
                            <p className="mt-1.5 text-sm text-muted-foreground">
                                {step.text}
                            </p>
                        </li>
                    ))}
                </ol>
            </Section>

            {/* Estudos de exemplo */}
            {examples.length > 0 && (
                <Section
                    title="Veja um estudo"
                    description="Abra um estudo de exemplo, do jeito que o aluno vê."
                    className="bg-card"
                >
                    <ul className="grid gap-4 md:grid-cols-2">
                        {examples.map((lesson) => (
                            <li key={lesson.id}>
                                <Link
                                    href={show(lesson.slug)}
                                    className="group flex h-full gap-5 rounded-2xl border border-border bg-background p-5 transition-colors hover:border-primary/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    {lesson.cover_url && (
                                        <img
                                            src={lesson.cover_url}
                                            alt=""
                                            loading="lazy"
                                            decoding="async"
                                            className="size-24 shrink-0 rounded-xl object-cover sm:size-28"
                                        />
                                    )}
                                    <span className="flex min-w-0 flex-col">
                                        {lesson.series && (
                                            <span className="text-xs font-medium text-primary">
                                                {lesson.series.title}
                                            </span>
                                        )}
                                        <span className="mt-1 font-serif text-xl font-semibold">
                                            {lesson.title}
                                        </span>
                                        {lesson.bible_reference && (
                                            <span className="mt-1 text-sm text-muted-foreground">
                                                {lesson.bible_reference}
                                            </span>
                                        )}
                                        {lesson.summary && (
                                            <span className="mt-3 line-clamp-3 text-sm text-muted-foreground">
                                                {lesson.summary}
                                            </span>
                                        )}
                                        <span className="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary">
                                            Abrir o estudo
                                            <ArrowRight
                                                className="size-4 transition-transform group-hover:translate-x-0.5"
                                                aria-hidden
                                            />
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                    <p className="mt-6">
                        <Link
                            href={library()}
                            className="text-sm font-medium text-primary hover:underline"
                        >
                            Ver a biblioteca
                        </Link>
                    </p>
                </Section>
            )}

            {/* Apoie */}
            <Section>
                <div className="grid gap-8 md:grid-cols-[1fr_1.1fr] md:items-center">
                    <div>
                        <span className="inline-flex size-11 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                            <Heart className="size-5" aria-hidden />
                        </span>
                        <h2 className="mt-4 font-serif text-3xl font-semibold tracking-tight">
                            Apoie a {brand.name}
                        </h2>
                        <p className="mt-3 text-muted-foreground">
                            A {brand.name} é gratuita para todas as igrejas e se
                            mantém com ofertas voluntárias. Se ela tem servido à
                            sua classe, você pode contribuir com qualquer valor
                            pelo Pix.
                        </p>
                    </div>
                    <div className="rounded-2xl border border-border bg-card p-5">
                        <CopyField
                            label="Chave Pix (aleatória)"
                            value={pixKey}
                        />
                        <Link
                            href={support()}
                            className="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                        >
                            Ver o QR Code e o Pix copia e cola
                            <ArrowRight className="size-4" aria-hidden />
                        </Link>
                    </div>
                </div>
            </Section>

            {/* Fechamento */}
            <Section className="bg-card">
                <div className="text-center">
                    <h2 className="font-serif text-3xl font-semibold tracking-tight text-balance">
                        Leve a lição da semana para o celular da sua classe
                    </h2>
                    <CallToAction
                        registration={features.registration}
                        className="justify-center"
                    />
                </div>
            </Section>
        </>
    );
}

function CallToAction({
    registration,
    className,
}: {
    registration: boolean;
    className?: string;
}) {
    return (
        <div className={`mt-8 flex flex-wrap gap-3 ${className ?? ''}`}>
            {registration && (
                <Button asChild size="lg">
                    <Link href={register()}>
                        Criar minha igreja <ArrowRight />
                    </Link>
                </Button>
            )}
            <Button asChild size="lg" variant="outline">
                <Link href={login()}>Entrar</Link>
            </Button>
        </div>
    );
}

function Section({
    title,
    description,
    className,
    children,
}: {
    title?: string;
    description?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <section className={className}>
            <div className="mx-auto max-w-6xl px-4 py-14 sm:px-6 md:py-20">
                {title && (
                    <div className="mb-10 max-w-2xl">
                        <h2 className="font-serif text-3xl font-semibold tracking-tight">
                            {title}
                        </h2>
                        {description && (
                            <p className="mt-2 text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                )}
                {children}
            </div>
        </section>
    );
}

/** Captura de tela num contorno de celular simples (sem sombra nem brilho). */
function PhoneShot({
    src,
    alt,
    className,
    priority = false,
}: {
    src: string;
    alt: string;
    className?: string;
    priority?: boolean;
}) {
    return (
        <div
            className={`overflow-hidden rounded-[2rem] border-[6px] border-foreground/85 bg-card dark:border-foreground/25 ${className ?? ''}`}
        >
            <img
                src={src}
                alt={alt}
                width={390}
                height={780}
                loading={priority ? 'eager' : 'lazy'}
                decoding="async"
                className="block h-auto w-full"
            />
        </div>
    );
}
