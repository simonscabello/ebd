import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BookMarked,
    CalendarCheck,
    Home,
    LayoutDashboard,
    LogIn,
    LogOut,
    Smartphone,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
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
import {
    account,
    home,
    install,
    library,
    login,
    logout,
    myWeek,
} from '@/routes';
import { dashboard } from '@/routes/admin';
import { UserAvatar } from '@/components/user-avatar';

export type NavLinkItem = {
    title: string;
    /** Rótulo curto do menu inferior, quando o título não cabe numa linha. */
    short?: string;
    href: string;
    icon: LucideIcon;
    /** Prefixo do endereço que deixa o item ativo. */
    match?: string;
    /** Ativo só no endereço exato. */
    exact?: boolean;
    /** Fica só no menu inferior do celular (no computador está no menu da conta). */
    mobileOnly?: boolean;
};

/**
 * Itens do app de estudo. A gestão não entra aqui: é uma área à parte, aberta
 * pelo botão "Gestão" do topo (ver AdminLayout).
 */
function useStudyNavItems(): NavLinkItem[] {
    const { auth } = usePage().props;

    const items: NavLinkItem[] = [
        { title: 'Início', href: home.url(), icon: Home },
    ];

    if (auth.user?.is_member) {
        items.push({
            title: 'Minha semana',
            short: 'Semana',
            href: myWeek.url(),
            icon: CalendarCheck,
            match: '/minha-semana',
        });
    }

    items.push({ title: 'Biblioteca', href: library.url(), icon: BookMarked });

    items.push(
        auth.user
            ? {
                  title: 'Perfil',
                  href: account.url(),
                  icon: UserRound,
                  match: '/conta',
                  mobileOnly: true,
              }
            : { title: 'Entrar', href: login.url(), icon: LogIn },
    );

    return items;
}

export function useIsActive() {
    const { currentUrl } = useCurrentUrl();

    return (item: NavLinkItem) =>
        item.exact
            ? currentUrl === item.href
            : item.match
              ? currentUrl.startsWith(item.match)
              : item.href === '/'
                ? currentUrl === '/' || currentUrl.startsWith('/licoes')
                : currentUrl.startsWith(
                      new URL(item.href, 'http://x').pathname,
                  );
}

/** Links do topo no computador (no celular ficam no menu inferior). */
export function TopNavLinks({
    items,
    label,
}: {
    items: NavLinkItem[];
    label: string;
}) {
    const isActive = useIsActive();

    return (
        <nav className="hidden items-center gap-1 md:flex" aria-label={label}>
            {items
                .filter((item) => !item.mobileOnly)
                .map((item) => (
                    <Link
                        key={item.title}
                        href={item.href}
                        aria-current={isActive(item) ? 'page' : undefined}
                        className={cn(
                            'rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            isActive(item) && 'bg-muted text-foreground',
                        )}
                    >
                        {item.title}
                    </Link>
                ))}
        </nav>
    );
}

export function TopBar() {
    const { auth, church } = usePage().props;
    const items = useStudyNavItems();

    return (
        <header className="sticky top-0 z-30 border-b border-border/70 bg-background/85 backdrop-blur supports-[backdrop-filter]:bg-background/70 print:hidden">
            <div className="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
                <Link
                    href={home()}
                    className="min-w-0 rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <AppLogo churchName={church.name} />
                </Link>

                <div className="flex items-center gap-2 md:gap-1">
                    <TopNavLinks items={items} label="Principal" />
                    {auth.user?.can_access_admin && (
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="md:ml-2"
                        >
                            <Link href={dashboard()}>
                                <LayoutDashboard /> Gestão
                            </Link>
                        </Button>
                    )}
                    {auth.user && <UserMenu />}
                </div>
            </div>
        </header>
    );
}

/**
 * Menu da conta (avatar). Na gestão, troca o atalho "Gestão" por "Voltar ao app".
 */
export function UserMenu({ inAdmin = false }: { inAdmin?: boolean }) {
    const { auth } = usePage().props;

    if (!auth.user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className="ml-1 rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label="Menu da conta"
            >
                <UserAvatar name={auth.user.name} src={auth.user.avatar_url} />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="font-normal">
                    <p className="font-medium">{auth.user.name}</p>
                    <p className="truncate text-xs text-muted-foreground">
                        {auth.user.email}
                    </p>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {inAdmin ? (
                    <>
                        <DropdownMenuItem asChild>
                            <Link href={home()} className="w-full">
                                <ArrowLeft /> Voltar ao app
                            </Link>
                        </DropdownMenuItem>
                        {auth.user.is_member && (
                            <DropdownMenuItem asChild>
                                <Link href={myWeek()} className="w-full">
                                    <CalendarCheck /> Minha semana
                                </Link>
                            </DropdownMenuItem>
                        )}
                    </>
                ) : (
                    auth.user.can_access_admin && (
                        <DropdownMenuItem asChild>
                            <Link href={dashboard()} className="w-full">
                                <LayoutDashboard /> Gestão
                            </Link>
                        </DropdownMenuItem>
                    )
                )}
                <DropdownMenuItem asChild>
                    <Link href={account()} className="w-full">
                        <UserRound /> Perfil
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href={install()} className="w-full">
                        <Smartphone /> Instalar o app
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        href={logout()}
                        as="button"
                        className="w-full"
                        data-test="logout-button"
                    >
                        <LogOut /> Sair
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * Navegação inferior no celular: alvo de toque grande e sempre ao alcance do polegar.
 *
 * Altura fixa (--app-bottom-nav) e rótulos numa linha só, mesmo com a fonte
 * do sistema aumentada. O fundo continua abaixo do menu para nada da página
 * aparecer por baixo dele.
 */
export function BottomNavBar({
    items,
    label,
}: {
    items: NavLinkItem[];
    label: string;
}) {
    const isActive = useIsActive();

    return (
        <nav
            className="fixed inset-x-0 bottom-0 z-30 border-t border-border/70 bg-background/95 pb-safe backdrop-blur after:absolute after:inset-x-0 after:top-full after:h-[50svh] after:bg-background md:hidden print:hidden"
            aria-label={label}
        >
            <ul className="mx-auto flex h-15 max-w-md items-stretch justify-around px-2 pt-1.5">
                {items.map((item) => {
                    const active = isActive(item);

                    return (
                        <li key={item.title} className="flex min-w-0 flex-1">
                            <Link
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                aria-label={item.short ? item.title : undefined}
                                className={cn(
                                    'flex w-full min-w-0 flex-col items-center gap-1 rounded-lg px-0.5 py-1.5 text-[11px] leading-none font-medium text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    active && 'text-primary',
                                )}
                            >
                                <item.icon
                                    className="size-[22px] shrink-0"
                                    strokeWidth={active ? 2.4 : 1.9}
                                />
                                <span className="max-w-full truncate">
                                    {item.short ?? item.title}
                                </span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

export function BottomNav() {
    return <BottomNavBar items={useStudyNavItems()} label="Principal" />;
}
