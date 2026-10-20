import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { index as churchesIndex } from '@/routes/owner/churches';
import { index as libraryIndex } from '@/routes/owner/library';

/** Abas da área do dono: as igrejas e a biblioteca oficial. */
export function OwnerTabs() {
    const { url } = usePage();
    const tabs = [
        { title: 'Igrejas', href: churchesIndex.url() },
        { title: 'Biblioteca oficial', href: libraryIndex.url() },
    ];

    return (
        <nav aria-label="Área do dono" className="mb-6 flex gap-2 border-b">
            {tabs.map((tab) => {
                const active = url.startsWith(tab.href);

                return (
                    <Link
                        key={tab.href}
                        href={tab.href}
                        aria-current={active ? 'page' : undefined}
                        className={cn(
                            '-mb-px border-b-2 px-3 py-2 text-sm font-medium',
                            active
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {tab.title}
                    </Link>
                );
            })}
        </nav>
    );
}
