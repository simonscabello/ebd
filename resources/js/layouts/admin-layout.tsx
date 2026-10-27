import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Check,
    ChevronDown,
    Church as ChurchIcon,
    Layers,
    LayoutDashboard,
    Library,
    Menu,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { AppLogoMark } from '@/components/app-logo';
import { UserMenu } from '@/components/app-navigation';
import { MiniAudioPlayer } from '@/components/lesson/mini-audio-player';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import { dashboard } from '@/routes/admin';
import { edit as churchEdit } from '@/routes/admin/church';
import { update as switchChurch } from '@/routes/admin/current-church';
import {
    index as classroomsIndex,
    show as classroomPage,
} from '@/routes/admin/classrooms';
import { index as lessonsIndex } from '@/routes/admin/lessons';
import { index as churchesIndex } from '@/routes/owner/churches';
import { index as seriesIndex } from '@/routes/admin/series';

/**
 * Área de gestão (backoffice): casca própria, separada do app de estudo.
 *
 * As classes ficam sempre à vista (barra lateral no computador, botões no
 * topo do celular): cada classe é uma página só, com a linha do tempo dos
 * domingos. Lições, séries e dados da igreja ficam abaixo, como apoio.
 */
export default function AdminLayout({ children }: { children: ReactNode }) {
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        // Sem menu inferior na gestão: nada precisa reservar espaço para ele.
        <div className="flex min-h-svh [--app-bottom-nav:0px]">
            <a
                href="#conteudo"
                className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-card focus:px-4 focus:py-2 focus:shadow"
            >
                Pular para o conteúdo
            </a>

            <aside className="sticky top-0 hidden h-svh w-64 shrink-0 overflow-y-auto border-r border-border/70 bg-card/60 md:block print:hidden">
                <AdminNav />
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-30 border-b border-border/70 bg-card/90 backdrop-blur supports-[backdrop-filter]:bg-card/80 md:hidden print:hidden">
                    <div className="flex h-14 items-center justify-between gap-2 px-4">
                        <Link
                            href={dashboard()}
                            className="flex min-w-0 items-center gap-2.5 rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <AppLogoMark />
                            <span className="text-[15px] font-semibold tracking-tight">
                                Gestão
                            </span>
                        </Link>
                        <div className="flex items-center gap-1">
                            <Dialog open={menuOpen} onOpenChange={setMenuOpen}>
                                <DialogTrigger asChild>
                                    <button
                                        type="button"
                                        className="flex size-11 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        aria-label="Abrir o menu da gestão"
                                    >
                                        <Menu className="size-5" />
                                    </button>
                                </DialogTrigger>
                                <DialogContent
                                    className="top-0 left-0 h-svh max-w-[19rem] translate-x-0 translate-y-0 content-start gap-0 overflow-y-auto rounded-none border-0 border-r p-0 sm:max-w-[19rem]"
                                    aria-describedby={undefined}
                                >
                                    <DialogTitle className="sr-only">
                                        Menu da gestão
                                    </DialogTitle>
                                    {/* Fecha ao escolher um destino. */}
                                    <div
                                        onClick={(event) =>
                                            (
                                                event.target as HTMLElement
                                            ).closest('a') && setMenuOpen(false)
                                        }
                                    >
                                        <AdminNav />
                                    </div>
                                </DialogContent>
                            </Dialog>
                            <UserMenu inAdmin />
                        </div>
                    </div>
                    <ClassChips />
                </header>

                <main id="conteudo" className="flex-1 pb-16">
                    {children}
                </main>
            </div>

            <MiniAudioPlayer />
        </div>
    );
}

/** Endereço da página da classe (ou de dentro dela) que está aberta. */
function useClassroomActive() {
    const { currentUrl } = useCurrentUrl();

    return (slug: string) => {
        const base = classroomPage.url(slug);

        return currentUrl === base || currentUrl.startsWith(`${base}/`);
    };
}

function NavItem({
    href,
    icon: Icon,
    active,
    children,
}: {
    href: string;
    icon?: LucideIcon;
    active: boolean;
    children: ReactNode;
}) {
    return (
        <Link
            href={href}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'flex min-h-10 items-center gap-2.5 rounded-lg px-3 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                active && 'bg-accent font-medium text-accent-foreground',
            )}
        >
            {Icon && <Icon className="size-4 shrink-0" />}
            <span className="truncate">{children}</span>
        </Link>
    );
}

/** Menu da gestão: o mesmo na barra lateral e na gaveta do celular. */
function AdminNav() {
    const { auth, church, adminClassrooms } = usePage().props;
    const { currentUrl } = useCurrentUrl();
    const isClassroom = useClassroomActive();
    const starts = (path: string) => currentUrl.startsWith(path);

    return (
        <nav aria-label="Gestão" className="flex min-h-full flex-col gap-1 p-3">
            <div className="flex min-w-0 items-center gap-2.5 px-2 pt-1 pr-10 pb-4 md:pr-2">
                <Link
                    href={dashboard()}
                    className="shrink-0 rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label="Visão geral da gestão"
                >
                    <AppLogoMark />
                </Link>
                <span className="flex min-w-0 flex-col items-start leading-tight">
                    <span className="text-[15px] font-semibold tracking-tight">
                        Gestão
                    </span>
                    <ChurchMenu />
                </span>
            </div>

            <NavItem
                href={dashboard.url()}
                icon={LayoutDashboard}
                active={currentUrl === dashboard.url()}
            >
                Visão geral
            </NavItem>

            <p className="px-3 pt-5 pb-1 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                Classes
            </p>
            {adminClassrooms.map((classroom) => (
                <NavItem
                    key={classroom.slug}
                    href={classroomPage.url(classroom.slug)}
                    active={isClassroom(classroom.slug)}
                >
                    {classroom.name}
                </NavItem>
            ))}
            <NavItem
                href={classroomsIndex.url()}
                icon={Users}
                active={currentUrl === classroomsIndex.url()}
            >
                Todas as classes
            </NavItem>

            <p className="px-3 pt-5 pb-1 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                Conteúdo
            </p>
            <NavItem
                href={lessonsIndex.url()}
                icon={Library}
                active={starts(lessonsIndex.url())}
            >
                Lições
            </NavItem>
            <NavItem
                href={seriesIndex.url()}
                icon={Layers}
                active={starts(seriesIndex.url())}
            >
                Séries
            </NavItem>

            <div className="mt-auto flex flex-col gap-1 pt-6">
                {church.can_edit && (
                    <NavItem
                        href={churchEdit.url()}
                        icon={ChurchIcon}
                        active={currentUrl === churchEdit.url()}
                    >
                        Dados da igreja
                    </NavItem>
                )}
                {auth.user?.is_owner && (
                    <NavItem
                        href={churchesIndex.url()}
                        icon={Building2}
                        active={starts('/dono')}
                    >
                        Dono
                    </NavItem>
                )}
                <NavItem href={home.url()} icon={ArrowLeft} active={false}>
                    Voltar ao app
                </NavItem>
                <div className="hidden items-center gap-2 px-1 pt-2 md:flex">
                    <UserMenu inAdmin />
                    <span className="truncate text-sm">{auth.user?.name}</span>
                </div>
            </div>
        </nav>
    );
}

/** Celular: as classes como botões logo abaixo do topo. */
function ClassChips() {
    const { adminClassrooms } = usePage().props;
    const isClassroom = useClassroomActive();

    if (adminClassrooms.length === 0) {
        return null;
    }

    return (
        <nav
            aria-label="Classes"
            className="flex [scrollbar-width:none] gap-2 overflow-x-auto scroll-fade-x px-4 pb-2.5"
        >
            {adminClassrooms.map((classroom) => {
                const active = isClassroom(classroom.slug);

                return (
                    <Link
                        key={classroom.slug}
                        href={classroomPage.url(classroom.slug)}
                        aria-current={active ? 'page' : undefined}
                        className={cn(
                            'inline-flex h-9 shrink-0 items-center rounded-full border px-4 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            active
                                ? 'border-transparent bg-accent text-accent-foreground'
                                : 'text-muted-foreground',
                        )}
                    >
                        {classroom.name}
                    </Link>
                );
            })}
        </nav>
    );
}

/**
 * Nome da igreja no cabeçalho. Para quem a administra, abre "Dados da
 * igreja" e, com duas ou mais, a troca da igreja atual.
 */
function ChurchMenu() {
    const { church } = usePage().props;

    if (!church.can_edit) {
        return (
            <span className="max-w-full truncate text-xs text-muted-foreground sm:max-w-[14rem]">
                {church.name}
            </span>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className="flex max-w-full items-center gap-0.5 rounded text-xs text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:max-w-[14rem]"
                aria-label={`Igreja: ${church.name}`}
            >
                <span className="truncate">{church.name}</span>
                <ChevronDown className="size-3 shrink-0" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-64">
                {church.choices.length > 1 && (
                    <>
                        <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                            Trocar de igreja
                        </DropdownMenuLabel>
                        {church.choices.map((choice) => (
                            <DropdownMenuItem
                                key={choice.id}
                                onSelect={() =>
                                    choice.id !== church.current_id &&
                                    router.put(switchChurch.url(), {
                                        church_id: choice.id,
                                    })
                                }
                            >
                                <Check
                                    className={
                                        choice.id === church.current_id
                                            ? ''
                                            : 'invisible'
                                    }
                                />
                                <span className="truncate">{choice.name}</span>
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                    </>
                )}
                <DropdownMenuItem asChild>
                    <Link href={churchEdit()} className="w-full">
                        <ChurchIcon /> Dados da igreja
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
