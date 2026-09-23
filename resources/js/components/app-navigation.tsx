import { Link, usePage } from '@inertiajs/react';
import {
    BookMarked,
    CalendarCheck,
    Home,
    LogIn,
    LogOut,
    PenLine,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AppLogo from '@/components/app-logo';
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
import { home, library, login, logout, myWeek } from '@/routes';
import { dashboard } from '@/routes/admin';
import { edit as editProfile } from '@/routes/profile';

type Item = { title: string; href: string; icon: LucideIcon; match?: string };

function useNavItems(): Item[] {
    const { auth } = usePage().props;

    const items: Item[] = [{ title: 'Início', href: home.url(), icon: Home }];

    if (auth.user?.is_student) {
        items.push({
            title: 'Minha semana',
            href: myWeek.url(),
            icon: CalendarCheck,
            match: '/minha-semana',
        });
    }

    items.push({ title: 'Biblioteca', href: library.url(), icon: BookMarked });

    if (auth.user?.can_access_admin) {
        items.push({
            title: 'Gestão',
            href: dashboard.url(),
            icon: PenLine,
            match: '/admin',
        });
    }

    items.push(
        auth.user
            ? {
                  title: 'Conta',
                  href: editProfile.url(),
                  icon: UserRound,
                  match: '/conta',
              }
            : { title: 'Entrar', href: login.url(), icon: LogIn },
    );

    return items;
}

function useIsActive() {
    const { currentUrl } = useCurrentUrl();

    return (item: Item) =>
        item.match
            ? currentUrl.startsWith(item.match)
            : item.href === '/'
              ? currentUrl === '/' || currentUrl.startsWith('/licoes')
              : currentUrl.startsWith(new URL(item.href, 'http://x').pathname);
}

export function TopBar() {
    const { auth, church } = usePage().props;
    const items = useNavItems();
    const isActive = useIsActive();

    return (
        <header className="sticky top-0 z-30 border-b border-border/70 bg-background/85 backdrop-blur supports-[backdrop-filter]:bg-background/70">
            <div className="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
                <Link
                    href={home()}
                    className="rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <AppLogo churchName={church.name} />
                </Link>

                <nav
                    className="hidden items-center gap-1 md:flex"
                    aria-label="Principal"
                >
                    {items
                        .filter((item) => item.title !== 'Conta')
                        .map((item) => (
                            <Link
                                key={item.title}
                                href={item.href}
                                className={cn(
                                    'rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground',
                                    isActive(item) &&
                                        'bg-muted text-foreground',
                                )}
                            >
                                {item.title}
                            </Link>
                        ))}
                    {auth.user && <UserMenu />}
                </nav>

                {auth.user && (
                    <div className="md:hidden">
                        <UserMenu />
                    </div>
                )}
            </div>
        </header>
    );
}

function UserMenu() {
    const { auth } = usePage().props;

    if (!auth.user) {
        return null;
    }

    const initials = auth.user.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className="ml-1 flex size-9 items-center justify-center rounded-full bg-accent text-sm font-semibold text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label="Menu da conta"
            >
                {initials}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="font-normal">
                    <p className="font-medium">{auth.user.name}</p>
                    <p className="truncate text-xs text-muted-foreground">
                        {auth.user.email}
                    </p>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={editProfile()} className="w-full">
                        <UserRound /> Minha conta
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
 */
export function BottomNav() {
    const items = useNavItems();
    const isActive = useIsActive();

    return (
        <nav
            className="fixed inset-x-0 bottom-0 z-30 border-t border-border/70 bg-background/95 pb-safe backdrop-blur md:hidden"
            aria-label="Principal"
        >
            <ul className="mx-auto flex max-w-md items-stretch justify-around px-2 pt-1.5">
                {items.map((item) => {
                    const active = isActive(item);

                    return (
                        <li key={item.title} className="flex-1">
                            <Link
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'flex flex-col items-center gap-0.5 rounded-lg py-1.5 text-[11px] font-medium text-muted-foreground',
                                    active && 'text-primary',
                                )}
                            >
                                <item.icon
                                    className="size-[22px]"
                                    strokeWidth={active ? 2.4 : 1.9}
                                />
                                {item.title}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
