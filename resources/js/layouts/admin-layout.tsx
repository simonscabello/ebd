import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Layers,
    LayoutDashboard,
    Library,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { AppLogoMark } from '@/components/app-logo';
import type { NavLinkItem } from '@/components/app-navigation';
import {
    BottomNavBar,
    TopNavLinks,
    UserMenu,
} from '@/components/app-navigation';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import { dashboard } from '@/routes/admin';
import { index as classroomsIndex } from '@/routes/admin/classrooms';
import { index as lessonsIndex } from '@/routes/admin/lessons';
import { index as seriesIndex } from '@/routes/admin/series';

/**
 * Área de gestão (backoffice): casca própria, separada do app de estudo.
 *
 * Só os assuntos da gestão aparecem aqui (painel, classes, lições, séries);
 * "Voltar ao app" leva de volta ao estudo (Início, Biblioteca).
 */
export default function AdminLayout({ children }: { children: ReactNode }) {
    const { church } = usePage().props;

    const items: NavLinkItem[] = [
        {
            title: 'Painel',
            href: dashboard.url(),
            icon: LayoutDashboard,
            exact: true,
        },
        { title: 'Classes', href: classroomsIndex.url(), icon: Users },
        { title: 'Lições', href: lessonsIndex.url(), icon: Library },
        { title: 'Séries', href: seriesIndex.url(), icon: Layers },
    ];

    return (
        <div className="flex min-h-svh flex-col">
            <a
                href="#conteudo"
                className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-card focus:px-4 focus:py-2 focus:shadow"
            >
                Pular para o conteúdo
            </a>

            <header className="sticky top-0 z-30 border-t-4 border-b border-t-primary border-b-border/70 bg-card/90 backdrop-blur supports-[backdrop-filter]:bg-card/80 print:hidden">
                <div className="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
                    <Link
                        href={dashboard()}
                        className="flex min-w-0 items-center gap-2.5 rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <AppLogoMark />
                        <span className="flex min-w-0 flex-col leading-tight">
                            <span className="text-[15px] font-semibold tracking-tight">
                                Gestão
                            </span>
                            <span className="max-w-[14rem] truncate text-xs text-muted-foreground">
                                {church.name}
                            </span>
                        </span>
                    </Link>

                    <div className="flex items-center gap-2 md:gap-1">
                        <TopNavLinks items={items} label="Gestão" />
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="md:ml-2"
                        >
                            <Link href={home()}>
                                <ArrowLeft />
                                <span className="sm:hidden">App</span>
                                <span className="hidden sm:inline">
                                    Voltar ao app
                                </span>
                            </Link>
                        </Button>
                        <UserMenu inAdmin />
                    </div>
                </div>
            </header>

            <main
                id="conteudo"
                className="flex-1 pb-[calc(var(--app-bottom-nav)+2rem)] md:pb-16"
            >
                {children}
            </main>

            <BottomNavBar items={items} label="Gestão" />
        </div>
    );
}
