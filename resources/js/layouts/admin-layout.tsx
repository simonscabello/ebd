import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import { index as classroomsIndex } from '@/routes/admin/classrooms';
import { index as lessonsIndex } from '@/routes/admin/lessons';
import { index as seriesIndex } from '@/routes/admin/series';

/**
 * Área de gestão: mesma casca do app + atalhos entre as seções.
 */
export default function AdminLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage().props;
    const { currentUrl } = useCurrentUrl();

    const items = [
        { title: 'Painel', href: dashboard.url(), exact: true },
        { title: 'Lições', href: lessonsIndex.url() },
        { title: 'Séries', href: seriesIndex.url() },
        ...(auth.user?.is_admin
            ? [{ title: 'Classes', href: classroomsIndex.url() }]
            : []),
    ];

    return (
        <div>
            <div className="border-b border-border/70 bg-card/50">
                <nav
                    className="mx-auto flex max-w-4xl gap-1 overflow-x-auto px-4 py-2 sm:px-6"
                    aria-label="Gestão"
                >
                    {items.map((item) => {
                        const active = item.exact
                            ? currentUrl === item.href
                            : currentUrl.startsWith(item.href);

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'shrink-0 rounded-full px-3.5 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground',
                                    active &&
                                        'bg-primary text-primary-foreground hover:text-primary-foreground',
                                )}
                            >
                                {item.title}
                            </Link>
                        );
                    })}
                </nav>
            </div>
            {children}
        </div>
    );
}
