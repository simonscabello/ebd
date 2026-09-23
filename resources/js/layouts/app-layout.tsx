import type { ReactNode } from 'react';
import { BottomNav, TopBar } from '@/components/app-navigation';

export default function AppLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-svh flex-col">
            <a
                href="#conteudo"
                className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-card focus:px-4 focus:py-2 focus:shadow"
            >
                Pular para o conteúdo
            </a>
            <TopBar />
            <main id="conteudo" className="flex-1 pb-28 md:pb-16">
                {children}
            </main>
            <BottomNav />
        </div>
    );
}
