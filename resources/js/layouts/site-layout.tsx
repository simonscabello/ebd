import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { brand } from '@/lib/brand';
import { help, home, library, login, register, support } from '@/routes';

/**
 * Casca do site público ("/" para visitantes e "Apoie"): cabeçalho simples
 * com Entrar / Criar minha igreja e rodapé com os links institucionais.
 */
export default function SiteLayout({ children }: { children: ReactNode }) {
    const { auth, features } = usePage().props;

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <a
                href="#conteudo"
                className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-card focus:px-4 focus:py-2 focus:shadow"
            >
                Pular para o conteúdo
            </a>
            <header className="sticky top-0 z-30 border-b border-border/70 bg-background/90 backdrop-blur supports-[backdrop-filter]:bg-background/75">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6">
                    <Link
                        href={home()}
                        className="min-w-0 rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <AppLogo />
                    </Link>
                    <nav
                        aria-label="Acesso"
                        className="flex items-center gap-2"
                    >
                        {auth.user ? (
                            <Button asChild size="sm">
                                <Link href={home()}>Abrir o app</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={login()}>Entrar</Link>
                                </Button>
                                {features.registration && (
                                    <Button
                                        asChild
                                        size="sm"
                                        className="hidden sm:inline-flex"
                                    >
                                        <Link href={register()}>
                                            Criar minha igreja
                                        </Link>
                                    </Button>
                                )}
                            </>
                        )}
                    </nav>
                </div>
            </header>

            <main id="conteudo" className="flex-1">
                {children}
            </main>

            <footer className="border-t border-border/70">
                <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-10 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                    <div className="space-y-1">
                        <p className="font-serif text-lg font-medium">
                            {brand.name}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {brand.tagline}. Gratuito para igrejas.
                        </p>
                    </div>
                    <nav
                        aria-label="Rodapé"
                        className="flex flex-wrap gap-x-6 gap-y-2 text-sm"
                    >
                        <Link
                            href={help()}
                            className="text-muted-foreground hover:text-foreground"
                        >
                            Como funciona
                        </Link>
                        <Link
                            href={library()}
                            className="text-muted-foreground hover:text-foreground"
                        >
                            Biblioteca
                        </Link>
                        <Link
                            href={support()}
                            className="text-muted-foreground hover:text-foreground"
                        >
                            Apoie a {brand.name}
                        </Link>
                        {!auth.user && (
                            <Link
                                href={login()}
                                className="text-muted-foreground hover:text-foreground"
                            >
                                Entrar
                            </Link>
                        )}
                    </nav>
                </div>
            </footer>
        </div>
    );
}
